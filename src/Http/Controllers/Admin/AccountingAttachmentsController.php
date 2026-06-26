<?php

namespace RMS\Accounting\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use RMS\Accounting\Models\AccountingAttachment;
use RMS\Accounting\Services\AccountingAttachmentService;

/**
 * آپلود و دانلود پیوست‌های خصوصی حسابداری (فایل رسید و غیره).
 */
class AccountingAttachmentsController extends Controller
{
    public function __construct(
        protected AccountingAttachmentService $attachments
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $maxKb = (int) config('accounting.attachments.max_size_kb', 10240);

        Validator::make($request->all(), [
            'file' => 'required|file|max:' . $maxKb,
        ])->validate();

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.attachments.upload_failed'),
            ], 422);
        }
        $adminId = \RMS\Accounting\Support\AuditActor::adminId();

        try {
            $att = $this->attachments->storeOrphan($file, $adminId ? (int) $adminId : null);
        } catch (\InvalidArgumentException $e) {
            $key = 'accounting::accounting.attachments.' . $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => __($key) !== $key ? __($key) : $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => trans('accounting::accounting.attachments.upload_failed'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'uuid' => $att->uuid,
            'original_name' => $att->original_name,
            'mime' => $att->mime,
            'message' => trans('accounting::accounting.attachments.upload_ok'),
        ]);
    }

    public function download(string $uuid)
    {
        $attachment = AccountingAttachment::query()->where('uuid', $uuid)->firstOrFail();

        return $this->attachments->streamDownload($attachment);
    }
}
