<?php

namespace RMS\Accounting\Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RMS\Accounting\Services\AccountingDataWipeService;
use RMS\Accounting\Services\AccountingWipe\WipeOptions;

final class AccountingDataWipeServiceTest extends TestCase
{
    private static bool $appBooted = false;

    private static function findComposerAutoload(): ?string
    {
        $dir = __DIR__;
        for ($i = 0; $i < 16; $i++) {
            $candidate = $dir.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }

    private static function bootstrapLaravelDatabase(): void
    {
        if (self::$appBooted) {
            return;
        }

        $autoload = self::findComposerAutoload();
        if ($autoload === null) {
            self::markTestSkipped('Composer vendor/autoload.php not found by walking up from '.__DIR__);
        }
        require_once $autoload;

        $app = new Container;
        $app->singleton('config', fn () => new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                    ],
                ],
            ],
        ]));
        $app->singleton('events', fn ($c) => new \Illuminate\Events\Dispatcher($c));
        $app->instance('app', $app);
        $app->instance(Container::class, $app);
        Facade::setFacadeApplication($app);

        $dbProvider = new DatabaseServiceProvider($app);
        $dbProvider->register();
        $dbProvider->boot();

        self::$appBooted = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootstrapLaravelDatabase();
        $this->resetSqliteSchema();
        $this->seedMinimalWipeScenario();
    }

    private function resetSqliteSchema(): void
    {
        Schema::dropAllTables();
    }

    private function seedMinimalWipeScenario(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('closing_document_id')->nullable();
        });

        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reversed_by_document_id')->nullable();
        });

        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_document_id');
        });

        Schema::create('manual_journals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('accounting_document_id')->nullable();
            $table->unsignedBigInteger('reversed_journal_id')->nullable();
        });

        Schema::create('manual_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manual_journal_id');
        });

        Schema::create('cost_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_document_id');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->nullable();
        });

        DB::table('fiscal_years')->insert(['id' => 1, 'closing_document_id' => 1]);
        DB::table('accounting_documents')->insert([
            ['id' => 1, 'reversed_by_document_id' => 2],
            ['id' => 2, 'reversed_by_document_id' => null],
        ]);
        DB::table('financial_ledgers')->insert(['id' => 1, 'accounting_document_id' => 1]);
        DB::table('manual_journals')->insert([
            'id' => 1,
            'fiscal_year_id' => 1,
            'accounting_document_id' => null,
            'reversed_journal_id' => null,
        ]);
        DB::table('manual_journal_lines')->insert(['id' => 1, 'manual_journal_id' => 1]);
        DB::table('cost_entries')->insert(['id' => 1, 'accounting_document_id' => 1]);
        DB::table('customer_payments')->insert(['id' => 1, 'document_id' => 1]);
    }

    #[Test]
    public function documents_mode_dry_run_reports_counts(): void
    {
        $svc = new AccountingDataWipeService;
        $result = $svc->run(WipeOptions::documents(true));

        $this->assertTrue($result->dryRun);
        $this->assertGreaterThan(0, $result->count('would_delete:accounting_documents'));
        $this->assertSame(1, $result->count('would_null:customer_payments.document_id'));
    }

    #[Test]
    public function documents_mode_execute_clears_gl_and_nulls_payment_link(): void
    {
        $svc = new AccountingDataWipeService;
        $svc->run(WipeOptions::documents(false));

        $this->assertSame(0, (int) DB::table('accounting_documents')->count());
        $this->assertSame(0, (int) DB::table('financial_ledgers')->count());
        $this->assertSame(0, (int) DB::table('fiscal_years')->count());
        $this->assertNull(DB::table('customer_payments')->value('document_id'));
    }
}
