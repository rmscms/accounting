<?php

namespace RMS\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RMS\Accounting\Models\AccountingDocument;

/**
 * Event سند حسابداری ثبت قطعی شد
 */
class DocumentPostedEvent
{
    use Dispatchable, SerializesModels;

    public AccountingDocument $document;
    public array $metadata;

    public function __construct(AccountingDocument $document, array $metadata = [])
    {
        $this->document = $document;
        $this->metadata = $metadata;
    }
}
