<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hardware\ActivateTrainerKitAction;
use App\Actions\Hardware\AssignTrainerKitDeviceAction;
use App\Actions\Hardware\RecordTrainerKitSaleAction;
use App\Actions\Hardware\RegisterTrainerKitInventoryAction;
use App\Actions\Hardware\UpdateTrainerKitMetadataAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\School;
use App\Models\TrainerKitInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterTrainerKitController extends Controller
{
    /**
     * Display a listing of physical trainer kit inventories.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TrainerKitInventory::class);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $batch = $request->query('batch');
        $schoolId = $request->query('school_id');

        $query = TrainerKitInventory::with(['school', 'device', 'registeredBy', 'latestSaleRecord.school']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhere('batch_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('saleRecords', function ($sq) use ($search) {
                        $sq->where('sale_reference', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($batch)) {
            $query->where('batch_number', $batch);
        }

        if (! empty($schoolId)) {
            $query->where('school_id', $schoolId);
        }

        $inventories = $query->orderByDesc('id')->paginate(15)->withQueryString();

        // High-level fleet metrics
        $totalCount = TrainerKitInventory::count();
        $registeredStockCount = TrainerKitInventory::where('status', TrainerKitInventory::STATUS_REGISTERED)->count();
        $soldCount = TrainerKitInventory::where('status', TrainerKitInventory::STATUS_SOLD)->count();
        $assignedCount = TrainerKitInventory::where('status', TrainerKitInventory::STATUS_ASSIGNED)->count();
        $activatedCount = TrainerKitInventory::where('status', TrainerKitInventory::STATUS_ACTIVATED)->count();

        $schools = School::orderBy('name')->get(['id', 'name', 'npsn', 'slug']);
        $batches = TrainerKitInventory::whereNotNull('batch_number')
            ->distinct()
            ->orderBy('batch_number')
            ->pluck('batch_number');

        return view('pages.master.trainer-kits.index', compact(
            'inventories',
            'search',
            'status',
            'batch',
            'schoolId',
            'totalCount',
            'registeredStockCount',
            'soldCount',
            'assignedCount',
            'activatedCount',
            'schools',
            'batches'
        ));
    }

    /**
     * Store a newly registered physical trainer kit serial in central inventory.
     */
    public function store(Request $request, RegisterTrainerKitInventoryAction $action): RedirectResponse
    {
        Gate::authorize('create', TrainerKitInventory::class);

        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:100', 'unique:trainer_kit_inventories,serial_number'],
            'model_name' => ['nullable', 'string', 'max:100'],
            'hardware_revision' => ['nullable', 'string', 'max:50'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['registered_by'] = Auth::id();
        $inventory = $action->execute($validated);

        $user = Auth::user();
        AuditLog::create([
            'school_id' => null,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.created',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $inventory->id,
            'diff' => [
                'serial_number' => $inventory->serial_number,
                'batch_number' => $inventory->batch_number,
                'model_name' => $inventory->model_name,
            ],
            'correlation_id' => (string) Str::uuid(),
            'details' => "Mendaftarkan unit hardware baru: {$inventory->serial_number} ({$inventory->model_name})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('master.trainer-kits.show', $inventory)
            ->with('status', "Nomor seri unit '{$inventory->serial_number}' berhasil didaftarkan ke inventaris pabrik.");
    }

    /**
     * Display the specified trainer kit inventory details, ownership, and audit trail.
     */
    public function show(TrainerKitInventory $inventory): View
    {
        Gate::authorize('view', $inventory);

        $inventory->load([
            'school',
            'device',
            'registeredBy',
            'saleRecords.school',
            'saleRecords.soldBy',
            'lifecycleLogs.user',
            'lifecycleLogs.fromSchool',
            'lifecycleLogs.toSchool',
            'lifecycleLogs.fromDevice',
            'lifecycleLogs.toDevice',
        ]);

        $schools = School::orderBy('name')->get(['id', 'name', 'npsn', 'slug']);

        // Devices available for assignment within the kit's assigned school
        $availableDevices = collect();
        if ($inventory->school_id) {
            $availableDevices = Device::where('school_id', $inventory->school_id)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'bench_location']);
        }

        return view('pages.master.trainer-kits.show', compact('inventory', 'schools', 'availableDevices'));
    }

    /**
     * Update the specification metadata of the trainer kit.
     */
    public function update(Request $request, TrainerKitInventory $inventory, UpdateTrainerKitMetadataAction $action): RedirectResponse
    {
        Gate::authorize('update', $inventory);

        $validated = $request->validate([
            'model_name' => ['required', 'string', 'max:100'],
            'hardware_revision' => ['nullable', 'string', 'max:50'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in([
                TrainerKitInventory::STATUS_REGISTERED,
                TrainerKitInventory::STATUS_SOLD,
                TrainerKitInventory::STATUS_ASSIGNED,
                TrainerKitInventory::STATUS_ACTIVATED,
                TrainerKitInventory::STATUS_MAINTENANCE,
                TrainerKitInventory::STATUS_DECOMMISSIONED,
            ])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['user_id'] = Auth::id();
        $updated = $action->execute($inventory, $validated);

        $user = Auth::user();
        AuditLog::create([
            'school_id' => $updated->school_id,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.updated',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $updated->id,
            'diff' => $validated,
            'correlation_id' => (string) Str::uuid(),
            'details' => "Memperbarui metadata unit trainer kit: {$updated->serial_number}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('master.trainer-kits.show', $updated)
            ->with('status', 'Metadata spesifikasi unit berhasil diperbarui.');
    }

    /**
     * Record a sale or transfer of the kit to an educational tenant school.
     */
    public function recordSale(Request $request, TrainerKitInventory $inventory, RecordTrainerKitSaleAction $action): RedirectResponse
    {
        Gate::authorize('recordSale', $inventory);

        $validated = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'sale_reference' => ['required', 'string', 'max:100'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['sold_by'] = Auth::id();
        $updated = $action->execute($inventory, $validated);

        $user = Auth::user();
        AuditLog::create([
            'school_id' => $updated->school_id,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.sold',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $updated->id,
            'diff' => $validated,
            'correlation_id' => (string) Str::uuid(),
            'details' => "Mencatat penjualan unit {$updated->serial_number} ke sekolah {$updated->school?->name} (Ref: {$validated['sale_reference']})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('master.trainer-kits.show', $updated)
            ->with('status', "Kepemilikan unit berhasil ditetapkan ke sekolah '{$updated->school?->name}'.");
    }

    /**
     * Assign the kit to an operational workstation device in the school.
     */
    public function assignDevice(Request $request, TrainerKitInventory $inventory, AssignTrainerKitDeviceAction $action): RedirectResponse
    {
        Gate::authorize('assignDevice', $inventory);

        $validated = $request->validate([
            'device_id' => ['nullable', 'exists:devices,id'],
        ]);

        $validated['user_id'] = Auth::id();
        $updated = $action->execute($inventory, $validated);

        $deviceName = $updated->device ? "{$updated->device->name} ({$updated->device->code})" : 'Dilepas';

        $user = Auth::user();
        AuditLog::create([
            'school_id' => $updated->school_id,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.device_assigned',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $updated->id,
            'diff' => $validated,
            'correlation_id' => (string) Str::uuid(),
            'details' => "Pengaitan workstation unit {$updated->serial_number}: {$deviceName}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('master.trainer-kits.show', $updated)
            ->with('status', 'Pengaitan unit ke workstation sekolah berhasil diperbarui.');
    }

    /**
     * Activate the trainer kit for school deployment and instructional IoT use.
     */
    public function activate(Request $request, TrainerKitInventory $inventory, ActivateTrainerKitAction $action): RedirectResponse
    {
        Gate::authorize('activate', $inventory);

        $notes = $request->input('notes');
        $updated = $action->execute($inventory, [
            'user_id' => Auth::id(),
            'notes' => $notes,
        ]);

        $user = Auth::user();
        AuditLog::create([
            'school_id' => $updated->school_id,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.activated',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $updated->id,
            'diff' => [
                'status' => $updated->status,
                'device_id' => $updated->device_id,
            ],
            'correlation_id' => (string) Str::uuid(),
            'details' => "Aktivasi registri platform unit trainer kit: {$updated->serial_number}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('master.trainer-kits.show', $updated)
            ->with('status', "Unit '{$updated->serial_number}' telah berhasil diaktivasi pada registri platform.");
    }

    /**
     * Remove an unassigned factory inventory unit from the catalog.
     */
    public function destroy(TrainerKitInventory $inventory): RedirectResponse
    {
        Gate::authorize('delete', $inventory);

        if ($inventory->status !== TrainerKitInventory::STATUS_REGISTERED || $inventory->saleRecords()->exists()) {
            return redirect()->route('master.trainer-kits.show', $inventory)
                ->with('error', 'Unit yang telah memiliki catatan penjualan atau dalam siklus aktif tidak dapat dihapus.');
        }

        $serial = $inventory->serial_number;
        $inventoryId = $inventory->id;
        $inventory->delete();

        $user = Auth::user();
        AuditLog::create([
            'school_id' => null,
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Super Administrator',
            'actor_role' => $user?->role ?? 'superuser',
            'action' => 'inventory.deleted',
            'target_type' => 'trainer_kit_inventory',
            'target_id' => (string) $inventoryId,
            'diff' => ['serial_number' => $serial],
            'correlation_id' => (string) Str::uuid(),
            'details' => "Menghapus unit stok pabrik tak terpakai: {$serial}",
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('master.trainer-kits.index')
            ->with('status', "Unit nomor seri '{$serial}' berhasil dihapus dari inventaris.");
    }
}
