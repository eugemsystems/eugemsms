<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Modules\Core\Domain\Registry\TemplateVariableRegistry;

/**
 * Variables and stock layouts for `invoice`, `receipt` and `statement`
 * documents (Book B FIN-03 BR-FIN-03-021). A school can replace any layout; the
 * variable set is what the data builders supply. Money is pre-formatted text
 * so a template never does arithmetic.
 */
final class FinanceDocumentTemplates
{
    public const INVOICE = 'invoice';

    public const RECEIPT = 'receipt';

    public const STATEMENT = 'statement';

    public static function registerVariables(): void
    {
        TemplateVariableRegistry::register(self::INVOICE, ['school.name', 'invoice.number', 'invoice.issue_date', 'invoice.due_date', 'invoice.currency', 'invoice.gross', 'invoice.discount', 'invoice.net', 'invoice.paid', 'invoice.balance', 'invoice.status', 'student.name', 'student.admission_number', 'billed_to', 'lines.*']);
        TemplateVariableRegistry::register(self::RECEIPT, ['school.name', 'receipt.number', 'receipt.date', 'receipt.currency', 'receipt.amount', 'receipt.payer', 'receipt.narration', 'student.name', 'tenders.*']);
        TemplateVariableRegistry::register(self::STATEMENT, ['school.name', 'account.name', 'currency', 'from', 'to', 'opening', 'closing', 'lines.*']);
    }

    public static function content(string $type): string
    {
        return match ($type) {
            self::INVOICE => <<<'TPL'
                <h1>{{ school.name }}</h1>
                <h2>Invoice {{ invoice.number }}</h2>
                <p>Issued {{ invoice.issue_date }} — due {{ invoice.due_date }}</p>
                <p>Learner: <strong>{{ student.name }}</strong> ({{ student.admission_number }})</p>
                <p>Billed to: {{ billed_to }}</p>
                <table>
                <tr><th>Description</th><th>Gross</th><th>Discount</th><th>Net</th></tr>
                @foreach(lines as line)
                <tr><td>{{ line.description }}</td><td>{{ line.gross }}</td><td>{{ line.discount }}</td><td>{{ line.net }}</td></tr>
                @endforeach
                </table>
                <p>Total {{ invoice.currency }} {{ invoice.net }} — paid {{ invoice.paid }} — balance <strong>{{ invoice.balance }}</strong></p>
                TPL,
            self::RECEIPT => <<<'TPL'
                <h1>{{ school.name }}</h1>
                <h2>Receipt {{ receipt.number }}</h2>
                <p>{{ receipt.date }}</p>
                <p>Received from {{ receipt.payer }} for {{ student.name }}</p>
                <table>
                @foreach(tenders as tender)
                <tr><td>{{ tender.type }}</td><td>{{ tender.reference }}</td><td>{{ tender.amount }}</td></tr>
                @endforeach
                </table>
                <p>Total {{ receipt.currency }} {{ receipt.amount }}</p>
                <p>{{ receipt.narration }}</p>
                TPL,
            default => <<<'TPL'
                <h1>{{ school.name }}</h1>
                <h2>Statement of account</h2>
                <p>{{ account.name }} — {{ currency }} — {{ from }} to {{ to }}</p>
                <p>Opening balance: {{ opening }}</p>
                <table>
                <tr><th>Date</th><th>Reference</th><th>Narration</th><th>Debit</th><th>Credit</th><th>Balance</th></tr>
                @foreach(lines as line)
                <tr><td>{{ line.date }}</td><td>{{ line.reference }}</td><td>{{ line.narration }}</td><td>{{ line.debit }}</td><td>{{ line.credit }}</td><td>{{ line.balance }}</td></tr>
                @endforeach
                </table>
                <p>Closing balance: <strong>{{ closing }}</strong></p>
                TPL,
        };
    }

    public static function money(int $minor): string
    {
        return number_format($minor / 100, 2);
    }
}
