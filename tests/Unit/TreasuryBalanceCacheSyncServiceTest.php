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
use RMS\Accounting\Services\DocumentService;
use RMS\Accounting\Services\LedgerService;
use RMS\Accounting\Services\TreasuryBalanceCacheSyncService;

final class TreasuryBalanceCacheSyncServiceTest extends TestCase
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
        Container::setInstance($app);
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
        $this->resetSchema();
        $this->seedScenario();
    }

    private function resetSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->nullable();
            $table->string('document_type')->nullable();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_document_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount_base', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('balance', 18, 4)->default(0);
            $table->string('currency_code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('balance', 18, 4)->default(0);
            $table->string('currency_code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function seedScenario(): void
    {
        DB::table('banks')->insert([
            'id' => 1,
            'name' => 'ملی شریف',
            'account_id' => 574,
            'balance' => 1400000,
            'currency_code' => 'IRT',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cash_boxes')->insert([
            'id' => 1,
            'name' => 'صندوق نمونه',
            'account_id' => 701,
            'balance' => 350000000,
            'currency_code' => 'IRT',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('accounting_documents')->insert([
            [
                'id' => 381,
                'document_number' => 'DOC-381',
                'document_type' => 'manual_journal',
                'status' => 'posted',
                'total_debit' => 250000000,
                'total_credit' => 250000000,
                'posted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 382,
                'document_number' => 'DOC-382',
                'document_type' => 'manual_journal',
                'status' => 'draft',
                'total_debit' => 99000000,
                'total_credit' => 99000000,
                'posted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('financial_ledgers')->insert([
            [
                'accounting_document_id' => 381,
                'account_id' => 574,
                'amount_base' => 250000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'accounting_document_id' => 381,
                'account_id' => 701,
                'amount_base' => -5000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'accounting_document_id' => 382,
                'account_id' => 574,
                'amount_base' => 99000000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    #[Test]
    public function sync_for_document_recalculates_only_posted_ledger_totals_for_treasury_accounts(): void
    {
        $svc = new TreasuryBalanceCacheSyncService;
        $svc->syncForDocument(381);

        $bankBalance = (float) DB::table('banks')->where('id', 1)->value('balance');
        $cashBalance = (float) DB::table('cash_boxes')->where('id', 1)->value('balance');

        $this->assertSame(250000000.0, $bankBalance);
        $this->assertSame(-5000000.0, $cashBalance);
    }

    #[Test]
    public function post_document_triggers_realtime_treasury_sync(): void
    {
        DB::table('banks')->where('id', 1)->update(['balance' => 123.0]);
        DB::table('cash_boxes')->where('id', 1)->update(['balance' => 456.0]);

        $svc = new DocumentService(new LedgerService, new TreasuryBalanceCacheSyncService);
        $ok = $svc->postDocument(382);

        $this->assertTrue($ok);
        $this->assertSame('posted', (string) DB::table('accounting_documents')->where('id', 382)->value('status'));
        // Document 382 has ledger only on account 574, and after posting both posted docs are included.
        $this->assertSame(349000000.0, (float) DB::table('banks')->where('id', 1)->value('balance'));
        $this->assertSame(456.0, (float) DB::table('cash_boxes')->where('id', 1)->value('balance'));
    }
}
