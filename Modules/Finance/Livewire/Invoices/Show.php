<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Invoices;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\RecordDocumentDownloadAction;
use Modules\Core\Domain\DataObjects\Documents\RecordDocumentDownloadData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RenderInvoiceDocumentAction;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ReceiptAllocation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `Finance\Invoices\Show` (Book B FIN-03 §5, `finance.invoice.view`) —
 * lines, calculation notes, allocations, and the journal link.
 */
#[Title('Invoice')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Invoice $invoice;

    public function mount(School $school, Invoice $invoice): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.invoice.view');
        $this->invoice = $invoice->load('lines.component', 'student', 'journal', 'replacedBy');
    }

    /**
     * Streams the printable invoice, rendering it first if issuance could not
     * (documents never blocked the invoice). Records the download (CORE-06).
     */
    public function download(): StreamedResponse
    {
        $this->authorizePermission('finance.invoice.view');

        $document = app(RenderInvoiceDocumentAction::class)->execute($this->invoice, (int) auth()->id());
        app(RecordDocumentDownloadAction::class)->execute(new RecordDocumentDownloadData($document->id));

        return Storage::disk('local')->download($document->file_path, str_replace(['/', '\\'], '-', $this->invoice->invoice_number).'.html');
    }

    public function render(): View
    {
        return view('finance::invoices.show', [
            'allocations' => ReceiptAllocation::where('invoice_id', $this->invoice->id)->with('receipt')->get(),
        ]);
    }
}
