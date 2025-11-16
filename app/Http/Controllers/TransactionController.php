<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\TransactionLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransactionsExport;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->input('filter', 'all');
        $type = $request->input('type', 'all');

        $query = TransactionLog::query()
            ->with(['reservation.spaceType', 'customer', 'processedBy'])
            ->orderByDesc('created_at');

        // Filter by type
        if ($type !== 'all') {
            $query->where('type', $type);
        }

        // Filter by date
        if ($filter !== 'all') {
            $this->applyDateFilter($query, $filter);
        }

        $transactions = $query->paginate(20)->through(fn ($log) => [
            'id' => $log->id,
            'type' => $log->type,
            'reservation_id' => $log->reservation_id,
            'reservation' => $log->reservation ? [
                'id' => $log->reservation->id,
                'space_type' => $log->reservation->spaceType ? [
                    'name' => $log->reservation->spaceType->name,
                ] : null,
            ] : null,
            'customer' => $log->customer ? [
                'id' => $log->customer->id,
                'name' => $log->customer->name ?? $log->customer->company_name,
            ] : null,
            'processed_by' => $log->processedBy ? [
                'id' => $log->processedBy->id,
                'name' => $log->processedBy->name,
            ] : null,
            'amount' => $log->amount,
            'payment_method' => $log->payment_method,
            'status' => $log->status,
            'reference_number' => $log->reference_number,
            'description' => $log->description,
            'notes' => $log->notes,
            'created_at' => $log->created_at,
        ]);

        $summary = [
            'totalPayments' => TransactionLog::where('type', 'payment')->sum('amount'),
            'totalRefunds' => abs(TransactionLog::where('type', 'refund')->sum('amount')),
            'netRevenue' => TransactionLog::where('type', 'payment')->sum('amount') + TransactionLog::where('type', 'refund')->sum('amount'),
            'totalCancellations' => TransactionLog::where('type', 'cancellation')->count(),
            'pendingRefunds' => \App\Models\Refund::where('status', 'pending')->count(),
        ];

        // Get pending payments (all reservations with unpaid balance)
        // Show ALL reservations with unpaid balance regardless of status
        // (Some may be marked "paid" but have zero amount_paid - data inconsistency)
        $pendingPayments = Reservation::query()
            ->with(['spaceType', 'customer', 'space'])
            ->whereNotNull('end_time')  // Must have ended
            ->where('cost', '>', 0)  // Must have a cost
            ->where(function($q) {
                // Has unpaid balance
                $q->whereNull('amount_paid')
                  ->orWhereRaw('amount_paid < cost');
            })
            ->whereNot('status', 'cancelled')  // Only exclude cancelled
            ->orderByDesc('end_time')
            ->get()
            ->map(fn ($reservation) => [
                'id' => $reservation->id,
                'space_type' => $reservation->spaceType ? [
                    'name' => $reservation->spaceType->name,
                ] : null,
                'space' => $reservation->space ? [
                    'name' => $reservation->space->name,
                ] : null,
                'customer' => $reservation->customer ? [
                    'id' => $reservation->customer->id,
                    'name' => $reservation->customer->name ?? $reservation->customer->company_name,
                ] : null,
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
                'hours' => $reservation->hours,
                'cost' => $reservation->cost,
                'amount_paid' => $reservation->amount_paid ?? 0,
                'balance' => $reservation->cost - ($reservation->amount_paid ?? 0),
                'is_open_time' => $reservation->is_open_time,
                'status' => $reservation->status,
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'pendingPayments' => $pendingPayments,
            'filters' => [
                'filter' => $filter,
                'type' => $type,
            ],
            'summary' => $summary,
        ]);
    }

    public function export(Request $request)
    {
        $filename = sprintf('transactions-all-reports-%s.xlsx', now()->format('Ymd_His'));

        return Excel::download(new TransactionsExport(), $filename);
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,partial,paid,completed,cancelled',
            'payment_method' => 'nullable|in:cash,gcash,maya,bank_transfer',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Update the reservation
        $reservation->update($validated);

        return back()->with('success', 'Transaction updated successfully.');
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
