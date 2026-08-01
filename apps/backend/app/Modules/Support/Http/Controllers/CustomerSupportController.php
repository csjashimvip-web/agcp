<?php

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CustomerSupportController
{
    public function index(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        return response()->json([
            'data' => DB::table('support_tickets')
                ->where('tenant_id', $tenant->id())
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'category' => [
                'required',
                Rule::in([
                    'general',
                    'order',
                    'wallet',
                    'payout',
                    'technical',
                ]),
            ],
            'priority' => [
                'nullable',
                Rule::in(['low', 'normal', 'high']),
            ],
            'message' => ['required', 'string', 'min:2', 'max:10000'],
        ]);

        $ticketId = DB::transaction(function () use (
            $validated,
            $tenant,
            $request,
        ): int {
            $id = DB::table('support_tickets')->insertGetId([
                'tenant_id' => $tenant->id(),
                'user_id' => $request->user()->id,
                'ticket_number' => 'SUP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'priority' => $validated['priority'] ?? 'normal',
                'status' => 'open',
                'last_activity_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('support_messages')->insert([
                'support_ticket_id' => $id,
                'user_id' => $request->user()->id,
                'is_internal' => false,
                'body' => $validated['message'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });

        return response()->json([
            'data' => DB::table('support_tickets')
                ->where('id', $ticketId)
                ->first(),
        ], 201);
    }

    public function reply(
        Request $request,
        int $ticketId,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:10000'],
        ]);

        $ticket = DB::table('support_tickets')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->where('id', $ticketId)
            ->first();

        abort_unless($ticket, 404);
        abort_if($ticket->status === 'closed', 422, 'Ticket is closed.');

        DB::transaction(function () use (
            $ticketId,
            $request,
            $validated,
        ): void {
            DB::table('support_messages')->insert([
                'support_ticket_id' => $ticketId,
                'user_id' => $request->user()->id,
                'is_internal' => false,
                'body' => $validated['message'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('support_tickets')
                ->where('id', $ticketId)
                ->update([
                    'status' => 'open',
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['data' => ['replied' => true]], 201);
    }
}