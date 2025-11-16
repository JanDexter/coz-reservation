<?php

namespace App\Exports;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class TransactionsExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TransactionsSummarySheet(),
            new TransactionsDetailSheet('daily'),
            new TransactionsDetailSheet('weekly'),
            new TransactionsDetailSheet('monthly'),
        ];
    }
}

class TransactionsSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Summary Report';
    }

    public function collection(): Collection
    {
        $summary = new Collection();

        // Generate summary for each period
        $periods = [
            'daily' => 'TODAY',
            'weekly' => 'THIS WEEK',
            'monthly' => 'THIS MONTH',
        ];

        foreach ($periods as $filter => $label) {
            $query = Reservation::with(['customer', 'space.spaceType', 'spaceType']);
            $this->applyDateFilter($query, $filter);
            $reservations = $query->get();

            $summary->push(["═══════════════════════════════════════════════════"]);
            $summary->push([$label . ' REPORT']);
            $summary->push(["═══════════════════════════════════════════════════"]);
            $summary->push(['']);
            
            // Overall statistics
            $summary->push(['OVERALL STATISTICS', '', '', '']);
            $summary->push(['Total Reservations', $reservations->count(), '', '']);
            $summary->push(['Total Revenue', '₱' . number_format($reservations->sum('total_cost'), 2), '', '']);
            $summary->push(['Average Transaction', '₱' . number_format($reservations->avg('total_cost') ?: 0, 2), '', '']);
            $summary->push(['Total Hours Booked', number_format($reservations->sum('hours'), 1) . ' hours', '', '']);
            $summary->push(['']);

            // Payment method breakdown
            $summary->push(['PAYMENT METHOD BREAKDOWN', 'Transactions', 'Revenue', '']);
            $paymentMethods = $reservations->groupBy('payment_method');
            foreach ($paymentMethods as $method => $items) {
                $method = $method ?: 'Not Specified';
                $count = $items->count();
                $revenue = $items->sum('total_cost');
                $summary->push([strtoupper($method), $count, '₱' . number_format($revenue, 2), '']);
            }
            $summary->push(['']);

            // Best selling space types
            $summary->push(['BEST SELLING SPACE TYPES', 'Bookings', 'Hours', 'Revenue']);
            $spaceTypes = $reservations->groupBy(function($r) {
                return $r->spaceType?->name ?? $r->space?->spaceType?->name ?? 'N/A';
            })->sortByDesc(function($items) {
                return $items->sum('total_cost');
            });
            
            $rank = 1;
            foreach ($spaceTypes->take(5) as $spaceType => $items) {
                $count = $items->count();
                $revenue = $items->sum('total_cost');
                $hours = $items->sum('hours');
                $summary->push([
                    "#$rank - $spaceType",
                    $count,
                    number_format($hours, 1),
                    '₱' . number_format($revenue, 2)
                ]);
                $rank++;
            }
            $summary->push(['']);

            // Peak hours analysis
            $summary->push(['PEAK BOOKING HOURS', 'Bookings', 'Revenue', '']);
            $hourlyBookings = $reservations->groupBy(function($r) {
                return Carbon::parse($r->start_time)->format('H:00');
            })->sortByDesc(function($items) {
                return $items->count();
            });
            
            foreach ($hourlyBookings->take(5) as $hour => $items) {
                $count = $items->count();
                $revenue = $items->sum('total_cost');
                $summary->push([
                    $hour,
                    $count,
                    '₱' . number_format($revenue, 2),
                    ''
                ]);
            }
            $summary->push(['']);

            // Top customers
            $summary->push(['TOP CUSTOMERS', 'Bookings', 'Revenue', '']);
            $customers = $reservations->groupBy(function($r) {
                return $r->customer?->name ?? $r->customer?->company_name ?? 'N/A';
            })->sortByDesc(function($items) {
                return $items->sum('total_cost');
            });
            
            $rank = 1;
            foreach ($customers->take(10) as $customer => $items) {
                $count = $items->count();
                $revenue = $items->sum('total_cost');
                $summary->push([
                    "#$rank - $customer",
                    $count,
                    '₱' . number_format($revenue, 2),
                    ''
                ]);
                $rank++;
            }
            $summary->push(['']);

            // Discount impact
            $discounted = $reservations->where('is_discounted', true);
            if ($discounted->count() > 0) {
                $summary->push(['DISCOUNT USAGE', '', '', '']);
                $summary->push(['Discounted Bookings', $discounted->count(), '', '']);
                $summary->push(['Discount Rate', number_format(($discounted->count() / $reservations->count()) * 100, 1) . '%', '', '']);
                $summary->push(['Revenue with Discounts', '₱' . number_format($discounted->sum('total_cost'), 2), '', '']);
                $summary->push(['']);
            }

            $summary->push(['', '', '', '']);
            $summary->push(['', '', '', '']);
        }

        return $summary;
    }

    public function headings(): array
    {
        return [];
    }

    protected function applyDateFilter($query, string $filter): void
    {
        switch ($filter) {
            case 'weekly':
                $query->whereBetween('start_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereBetween('start_time', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'daily':
            default:
                $query->whereDate('start_time', Carbon::today());
                break;
        }
    }
}

class TransactionsDetailSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly string $filter = 'daily') {}

    public function title(): string
    {
        return match($this->filter) {
            'weekly' => 'Weekly Transactions',
            'monthly' => 'Monthly Transactions',
            default => 'Daily Transactions',
        };
    }

    public function collection(): Collection
    {
        $query = Reservation::with(['customer', 'space.spaceType', 'spaceType'])->orderByDesc('created_at');
        $this->applyDateFilter($query, $this->filter);
        return $query->get();
    }

    public function headings(): array
    {
        $periodLabel = match($this->filter) {
            'weekly' => 'This Week (' . Carbon::now()->startOfWeek()->format('M d') . ' - ' . Carbon::now()->endOfWeek()->format('M d, Y') . ')',
            'monthly' => Carbon::now()->format('F Y'),
            default => Carbon::today()->format('F d, Y'),
        };

        return [
            ['TRANSACTIONS FOR: ' . strtoupper($periodLabel)],
            [],
            ['Date', 'Customer', 'Space Type', 'Space', 'Hours', 'Payment Method', 'Status', 'Discounted', 'Total Cost'],
        ];
    }

    public function map($reservation): array
    {
        return [
            optional($reservation->created_at)->format('Y-m-d H:i'),
            $reservation->customer?->name ?? $reservation->customer?->company_name ?? 'N/A',
            $reservation->spaceType?->name ?? $reservation->space?->spaceType?->name ?? 'N/A',
            $reservation->space?->name ?? 'N/A',
            $reservation->hours,
            strtoupper($reservation->payment_method ?? 'N/A'),
            $reservation->status,
            $reservation->is_discounted ? 'Yes' : 'No',
            '₱' . number_format($reservation->total_cost, 2),
        ];
    }

    protected function applyDateFilter($query, string $filter): void
    {
        switch ($filter) {
            case 'weekly':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'monthly':
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case 'daily':
            default:
                $query->whereDate('created_at', Carbon::today());
                break;
        }
    }
}
