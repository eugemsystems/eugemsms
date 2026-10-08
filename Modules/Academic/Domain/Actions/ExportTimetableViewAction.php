<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ExportTimetableViewData;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;

/**
 * ACT-ExportTimetableView (Book E ACA-03 §7/BR-ACA-03-022). Renders
 * `Timetable\Views`' own by-class/teacher/venue table to a PDF — the
 * same `Pdf::loadHTML()->output()` pattern
 * `Modules\Intelligence\Domain\Actions\GenerateReportExportAction`
 * already uses, now that `barryvdh/laravel-dompdf` is a dependency.
 * "Printable and exportable" used to stop at the browser's own print
 * dialog; this is the export half BR-ACA-03-022 also names.
 */
final class ExportTimetableViewAction extends Action
{
    protected bool $transactional = false;

    public function execute(ExportTimetableViewData $data): string
    {
        $timetable = Timetable::findOrFail($data->timetableId);

        $column = match ($data->mode) {
            'teacher' => 'staff_id',
            'venue' => 'venue_id',
            'class' => 'class_id',
            default => throw new InvalidArgumentException("[{$data->mode}] is not a timetable view mode."),
        };

        $targetLabel = match ($data->mode) {
            'teacher' => ($staff = Staff::find($data->targetId)) !== null ? trim("{$staff->first_name} {$staff->last_name}") : '',
            'venue' => ($venue = Venue::find($data->targetId)) !== null ? $venue->name : '',
            default => ($class = SchoolClass::find($data->targetId)) !== null ? $class->name : '',
        };

        $slots = TimetableSlot::where('timetable_id', $timetable->id)
            ->where($column, $data->targetId)
            ->with(['subject', 'staff', 'schoolClass', 'venue'])
            ->orderBy('cycle_day')->orderBy('period_number')
            ->get();

        $rows = $slots->map(fn (TimetableSlot $slot): array => [
            'day' => $slot->cycle_day,
            'period' => $slot->period_number,
            'subject' => (string) $slot->subject?->name,
            'teacher' => trim("{$slot->staff?->first_name} {$slot->staff?->last_name}"),
            'class' => (string) $slot->schoolClass?->name,
            'venue' => (string) $slot->venue?->name,
        ])->values()->all();

        $html = $this->toHtml($timetable->name, ucfirst($data->mode), $targetLabel, $rows);

        return Pdf::loadHTML($html)->output();
    }

    /**
     * @param  array<int, array{day: int, period: int, subject: string, teacher: string, class: string, venue: string}>  $rows
     */
    private function toHtml(string $timetableName, string $modeLabel, string $targetLabel, array $rows): string
    {
        $body = implode('', array_map(fn (array $row): string => '<tr>'
            .'<td>'.e((string) $row['day']).'</td>'
            .'<td>'.e((string) $row['period']).'</td>'
            .'<td>'.e($row['subject']).'</td>'
            .'<td>'.e($row['teacher']).'</td>'
            .'<td>'.e($row['class']).'</td>'
            .'<td>'.e($row['venue']).'</td>'
            .'</tr>', $rows));

        return '<h3>'.e($timetableName).'</h3><p>'.e($modeLabel).': '.e($targetLabel).'</p>'
            .'<table border="1" cellspacing="0" cellpadding="4">'
            .'<thead><tr><th>Day</th><th>Period</th><th>Subject</th><th>Teacher</th><th>Class</th><th>Venue</th></tr></thead>'
            .'<tbody>'.$body.'</tbody></table>';
    }
}
