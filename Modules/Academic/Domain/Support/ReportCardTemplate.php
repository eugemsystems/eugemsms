<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Modules\Core\Domain\Registry\TemplateVariableRegistry;

/**
 * The variables and the stock layout for `report_card` and `transcript`
 * documents (Book D ACA-05 §6, BR-ACA-05-016/022). A school may replace the
 * layout; the variable set is what the data builders supply.
 */
final class ReportCardTemplate
{
    public const REPORT_TYPE = 'report_card';

    public const TRANSCRIPT_TYPE = 'transcript';

    public static function registerVariables(): void
    {
        TemplateVariableRegistry::register(self::REPORT_TYPE, [
            'school.name', 'student.name', 'student.admission_number', 'class.name', 'term.name', 'year.name',
            'summary.average_percent', 'summary.subjects_taken', 'summary.subjects_passed', 'summary.class_position', 'summary.class_size',
            'summary.level_position', 'summary.level_size', 'summary.attendance_percent', 'summary.conduct_grade', 'summary.promotion',
            'summary.class_teacher_comment', 'summary.head_comment', 'show_positions', 'show_class_average', 'version', 'subjects.*',
        ]);

        TemplateVariableRegistry::register(self::TRANSCRIPT_TYPE, [
            'school.name', 'student.name', 'student.admission_number', 'student.date_of_birth', 'issued_on', 'terms.*',
        ]);
    }

    public static function reportContent(): string
    {
        return <<<'TPL'
            <h1>{{ school.name }}</h1>
            <h2>Report card — {{ term.name }} {{ year.name }}</h2>
            <p><strong>{{ student.name }}</strong> ({{ student.admission_number }}) — {{ class.name }}</p>
            <table>
            <tr><th>Subject</th><th>Mark %</th><th>Grade</th>@if(show_class_average)<th>Class avg</th>@endif@if(show_positions)<th>Position</th>@endif<th>Comment</th></tr>
            @foreach(subjects as subject)
            <tr><td>{{ subject.name }}</td><td>{{ subject.final_percent }}</td><td>{{ subject.grade }}</td>@if(show_class_average)<td>{{ subject.class_average }}</td>@endif@if(show_positions)<td>{{ subject.position }}</td>@endif<td>{{ subject.comment }}</td></tr>
            @endforeach
            </table>
            <p>Average: {{ summary.average_percent }}% — subjects passed {{ summary.subjects_passed }} of {{ summary.subjects_taken }}</p>
            @if(show_positions)<p>Class position: {{ summary.class_position }} of {{ summary.class_size }}</p>@endif
            <p>Attendance: {{ summary.attendance_percent }}% — Conduct: {{ summary.conduct_grade }}</p>
            <p>Promotion: {{ summary.promotion }}</p>
            <p>Class teacher: {{ summary.class_teacher_comment }}</p>
            <p>Head: {{ summary.head_comment }}</p>
            TPL;
    }

    public static function transcriptContent(): string
    {
        return <<<'TPL'
            <h1>{{ school.name }}</h1>
            <h2>Academic transcript</h2>
            <p><strong>{{ student.name }}</strong> ({{ student.admission_number }}), born {{ student.date_of_birth }}</p>
            @foreach(terms as term)
            <h3>{{ term.year }} — {{ term.name }} ({{ term.class }})</h3>
            <table>
            <tr><th>Subject</th><th>Mark %</th><th>Grade</th></tr>
            @foreach(term.subjects as subject)
            <tr><td>{{ subject.name }}</td><td>{{ subject.final_percent }}</td><td>{{ subject.grade }}</td></tr>
            @endforeach
            </table>
            <p>Average {{ term.average_percent }}% — {{ term.promotion }}</p>
            @endforeach
            <p>Issued {{ issued_on }}</p>
            TPL;
    }
}
