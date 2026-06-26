<?php

namespace RMS\Accounting\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Accounting\Models\AccountingAttachment;
use RMS\Accounting\Models\Expense;

class AccountingAttachmentService
{
    public function storeOrphan(UploadedFile $file, ?int $adminUserId): AccountingAttachment
    {
        return $this->persistUpload($file, $adminUserId, null, null);
    }

    public function storeForExpense(Expense $expense, UploadedFile $file, ?int $adminUserId): AccountingAttachment
    {
        return $this->persistUpload($file, $adminUserId, Expense::class, (int) $expense->getKey());
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function storeManyForExpense(Expense $expense, array $files, ?int $adminUserId): void
    {
        $max = $this->maxPerExpense();
        $current = $expense->attachments()->count();
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            if ($current >= $max) {
                break;
            }
            $this->storeForExpense($expense, $file, $adminUserId);
            $current++;
        }
    }

    /**
     * پیوند پیوست‌های قبلاً آپلودشده (یتیم) به هزینه پس از ذخیره.
     *
     * @param  array<int, string>  $uuids
     */
    public function linkOrphansToExpense(Expense $expense, array $uuids, ?int $adminUserId): void
    {
        $uuids = array_values(array_unique(array_filter(array_map('strval', $uuids))));
        if ($uuids === []) {
            return;
        }

        $max = $this->maxPerExpense();
        $current = $expense->attachments()->count();

        $orphans = AccountingAttachment::query()
            ->whereIn('uuid', $uuids)
            ->whereNull('attachable_id')
            ->get();

        foreach ($orphans as $attachment) {
            if ($current >= $max) {
                break;
            }
            if ($adminUserId !== null && $attachment->uploaded_by !== null && (int) $attachment->uploaded_by !== $adminUserId) {
                continue;
            }
            $attachment->attachable()->associate($expense);
            $attachment->save();
            $current++;
        }
    }

    /**
     * به‌روزرسانی: نگه‌داشتن uuidهای انتخاب‌شده و افزودن فایل‌های جدید؛ بقیه نرم‌حذف.
     *
     * @param  array<int, string>  $keepUuids
     * @param  array<int, UploadedFile>  $newFiles
     */
    public function syncExpenseAttachments(Expense $expense, array $keepUuids, array $newFiles, ?int $adminUserId): void
    {
        $keepUuids = array_values(array_unique(array_filter(array_map('strval', $keepUuids))));

        $existing = $expense->attachments()->get();
        foreach ($existing as $att) {
            if (! in_array($att->uuid, $keepUuids, true)) {
                $this->deleteAttachment($att);
            }
        }

        $this->storeManyForExpense($expense, $newFiles, $adminUserId);
    }

    public function deleteAttachment(AccountingAttachment $attachment): void
    {
        $disk = Storage::disk($attachment->disk);
        if ($attachment->path && $disk->exists($attachment->path)) {
            $disk->delete($attachment->path);
        }
        $attachment->delete();
    }

    public function streamDownload(AccountingAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk);
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
        ]);
    }

    protected function persistUpload(UploadedFile $file, ?int $adminUserId, ?string $attachableType, ?int $attachableId): AccountingAttachment
    {
        $this->validateFile($file);

        $uuid = (string) Str::uuid();
        $diskName = (string) config('accounting.attachments.disk', 'local');
        $baseDir = trim((string) config('accounting.attachments.directory', 'accounting/private'), '/');
        $year = date('Y');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $safeBase = substr($safeBase, 0, 80);
        $filename = $safeBase . '-' . substr(sha1($uuid), 0, 8) . '.' . $ext;

        $relativeDir = $baseDir . '/' . $year . '/' . $uuid;
        $disk = Storage::disk($diskName);
        $path = $disk->putFileAs($relativeDir, $file, $filename);

        if (! $path) {
            throw new \RuntimeException('Failed to store accounting attachment.');
        }

        $attachment = new AccountingAttachment([
            'uuid' => $uuid,
            'disk' => $diskName,
            'path' => $path,
            'original_name' => $file->getClientOriginalName() ?: $filename,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'uploaded_by' => $adminUserId,
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
        ]);
        $attachment->save();

        return $attachment;
    }

    protected function validateFile(UploadedFile $file): void
    {
        $maxKb = (int) config('accounting.attachments.max_size_kb', 10240);
        if ($file->getSize() > $maxKb * 1024) {
            throw new \InvalidArgumentException('file_too_large');
        }

        $allowed = array_values(array_unique(array_map('strtolower', array_filter((array) config(
            'accounting.attachments.allowed_mimes',
            ['jpg', 'jpeg', 'png', 'webp', 'pdf']
        )))));

        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: ''));
        if ($ext === '') {
            $ext = strtolower((string) ($file->guessExtension() ?: ''));
        }

        if ($ext !== '' && in_array($ext, $allowed, true)) {
            return;
        }

        $mime = strtolower((string) ($file->getMimeType() ?: ''));
        $clientMime = strtolower((string) ($file->getClientMimeType() ?: ''));

        foreach ([$mime, $clientMime] as $m) {
            if ($m !== '' && $this->mimeMatchesAllowedExtensions($m, $allowed)) {
                return;
            }
        }

        // PDF گاهی با application/octet-stream یا بدون پسوند درست می‌آید؛ فقط اگر pdf در config مجاز باشد.
        if (in_array('pdf', $allowed, true) && $this->fileStartsWithPdfMagic($file)) {
            return;
        }

        throw new \InvalidArgumentException('file_type_not_allowed');
    }

    /**
     * @param  array<int, string>  $allowedExt
     */
    protected function mimeMatchesAllowedExtensions(string $mime, array $allowedExt): bool
    {
        $map = [
            'application/pdf' => ['pdf'],
            'application/x-pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/jpg' => ['jpg', 'jpeg'],
            'image/pjpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/x-png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if (! isset($map[$mime])) {
            return false;
        }

        foreach ($map[$mime] as $e) {
            if (in_array($e, $allowedExt, true)) {
                return true;
            }
        }

        return false;
    }

    protected function fileStartsWithPdfMagic(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return false;
        }

        $head = @file_get_contents($path, false, null, 0, 5);

        return is_string($head) && str_starts_with($head, '%PDF');
    }

    protected function maxPerExpense(): int
    {
        return max(1, (int) config('accounting.attachments.max_per_expense', 5));
    }
}
