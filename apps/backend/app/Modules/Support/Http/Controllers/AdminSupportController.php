<?php

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminSupportController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => DB::table('support_tickets')
                ->join('users', 'users.id', '=', 'support_tickets.user_id')
                ->where('support_tickets.tenant_id', $tenant->id())
                ->orderByDesc('support_tickets.last_activity_at')
                ->limit(300)
                ->get([
                    'support_tickets.id',
                    'support_tickets.ticket_number',
                    'support_tickets.subject',
                    'support_tickets.category',
                    'support_tickets.priority',
                    'support_tickets.status',
                    'support_tickets.last_activity_at',
                    'users.name as user_name',
                    'users.email as user_email',
                ]),
        ]);
    }

    public function reply(
        Request $request,
        int $ticketId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:10000'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $ticket = DB::table('support_tickets')
            ->where('tenant_id', $tenant->id())
            ->where('id', $ticketId)
            ->first();

        abort_unless($ticket, 404);

        DB::transaction(function () use (
            $ticketId,
            $request,
            $validated,
        ): void {
            DB::table('support_messages')->insert([
                'support_ticket_id' => $ticketId,
                'user_id' => $request->user()->id,
                'is_internal' => $validated['internal'] ?? false,
                'body' => $validated['message'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('support_tickets')
                ->where('id', $ticketId)
                ->update([
                    'status' => 'pending_customer',
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        $audit->record(
            $request,
            $tenant->id(),
            'support.ticket.replied',
            'support_ticket',
            $ticketId,
            ['internal' => $validated['internal'] ?? false],
        );

        return response()->json(['data' => ['replied' => true]], 201);
    }

    public function update(
        Request $request,
        int $ticketId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'open',
                    'pending_customer',
                    'pending_internal',
                    'resolved',
                    'closed',
                ]),
            ],
            'priority' => [
                'nullable',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
        ]);

        abort_unless(
            DB::table('support_tickets')
                ->where('tenant_id', $tenant->id())
                ->where('id', $ticketId)
                ->exists(),
            404
        );

        $updates = $validated;
        $updates['last_activity_at'] = now();
        $updates['updated_at'] = now();

        if (($validated['status'] ?? null) === 'closed') {
            $updates['closed_at'] = now();
        }

        DB::table('support_tickets')
            ->where('id', $ticketId)
            ->update($updates);

        $audit->record(
            $request,
            $tenant->id(),
            'support.ticket.updated',
            'support_ticket',
            $ticketId,
            $validated,
        );

        return response()->json([
            'data' => DB::table('support_tickets')
                ->where('id', $ticketId)
                ->first(),
        ]);
    }
}