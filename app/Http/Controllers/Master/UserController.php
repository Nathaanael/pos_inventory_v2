<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $role = $request->query('role');

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when(array_key_exists((string) $role, User::ROLES), fn ($q) => $q->where('role', $role))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.users', [
            'title' => 'Pengguna',
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['must_reset_password'] = false;

        User::create($data);

        return back()->with('success', 'Berhasil ditambah');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // Role & status akun sendiri tidak boleh diubah (agar admin tidak mengunci dirinya).
        if ($user->is($request->user())) {
            unset($data['role'], $data['is_active']);
        }

        $user->update($data);

        return back()->with('success', 'Berhasil disimpan');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Anda tidak bisa menghapus akun sendiri.');

        $user->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => [Rule::requiredIf(! $user?->is($request->user())), Rule::in(array_keys(User::ROLES))],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
        ], [], [
            'name' => 'Nama',
            'email' => 'Email',
            'role' => 'Role',
            'phone' => 'No. HP',
            'password' => 'Password',
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
