@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Models\User')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pengguna" />

    <div x-data="crudPage({
            blank: { name: '', email: '', role: @js(User::ROLE_KASIR), phone: '', password: '', is_active: true, is_self: false },
            routes: { store: @js(route('users.store')), update: @js(route('users.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })"
        x-init="if (isEdit && editId == {{ auth()->id() }}) form.is_self = true">
        <x-common.table-card title="Daftar Pengguna" :paginator="$users">
            <x-slot:filters>
                <x-common.form.select name="role" class="h-10 sm:w-40" :value="request('role')" placeholder="Semua role"
                    :options="User::ROLES" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter role" />
            </x-slot:filters>

            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Pengguna
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-48">Nama</th>
                    <th class="table-th min-w-56">Email</th>
                    <th class="table-th min-w-24">Role</th>
                    <th class="table-th min-w-32">No. HP</th>
                    <th class="table-th min-w-20">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($users as $user)
                    @php $isSelf = $user->is(auth()->user()); @endphp
                    <tr class="table-row">
                        <td class="table-td">
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                                    {{ collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
                                </span>
                                <span class="font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ $user->name }}</span>
                                @if ($isSelf)
                                    <x-ui.badge size="sm" color="gray">Anda</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $user->email }}</td>
                        <td class="table-td">
                            <x-ui.badge size="sm" :color="$user->role === User::ROLE_KASIR ? 'info' : 'warning'">
                                {{ User::ROLES[$user->role] ?? ucfirst($user->role) }}
                            </x-ui.badge>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $user->phone ?: '-' }}</td>
                        <td class="table-td">@include('pages.master.partials.boolean-icon', ['value' => $user->is_active])</td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from([...$user->only(['id', 'name', 'email', 'role', 'phone', 'is_active']), 'is_self' => $isSelf]) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                @unless ($isSelf)
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Pengguna', message: 'Apakah Anda yakin ingin menghapus pengguna ini?', action: {{ Js::from(route('users.destroy', $user)) }} })">
                                        <x-svg.trash-bin /> Hapus
                                    </x-ui.dropdown-menu.item>
                                @endunless
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="6" title="Belum ada pengguna" />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()" max-width="2xl">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna'"></span></x-slot:heading>

            <form id="user-form" method="POST" x-bind:action="action" class="grid grid-cols-1 gap-5 sm:grid-cols-2" autocomplete="off">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nama" for="user-name" error="name" required>
                    <x-ui.input id="user-name" name="name" x-model="form.name" maxlength="100" required :aria-invalid="$errors->has('name') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Email (untuk login)" for="user-email" error="email" required>
                    <x-ui.input type="email" id="user-email" name="email" x-model="form.email" required :aria-invalid="$errors->has('email') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Role" for="user-role" error="role" required>
                    <x-common.form.select id="user-role" name="role" x-model="form.role" :options="User::ROLES" x-bind:disabled="form.is_self" required />
                    <p class="text-xs text-gray-500 dark:text-gray-400"
                        x-text="form.is_self ? 'Anda tidak bisa mengubah role akun sendiri.' : 'Kasir hanya bisa membuka halaman kasir (/kasir).'"></p>
                </x-common.form.field>

                <x-common.form.field label="No. HP" for="user-phone" error="phone">
                    <x-ui.input type="tel" id="user-phone" name="phone" x-model="form.phone" maxlength="20" />
                </x-common.form.field>

                <x-common.form.field label="Password" for="user-password" error="password">
                    <div x-data="{ show: false }" class="relative">
                        <x-ui.input id="user-password" name="password" x-model="form.password" x-bind:type="show ? 'text' : 'password'" type="password"
                            minlength="6" autocomplete="new-password" x-bind:required="!isEdit" class="pe-11"
                            :aria-invalid="$errors->has('password') ? 'true' : null" />
                        <button type="button" x-on:click="show = !show" x-bind:aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                            class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                            <x-svg.eye class="size-5" x-show="!show" />
                            <x-svg.eye-close class="size-5" x-show="show" x-cloak />
                        </button>
                    </div>
                    <p x-show="isEdit" class="text-xs text-gray-500 dark:text-gray-400">Kosongkan jika tidak ingin mengganti password.</p>
                </x-common.form.field>

                <x-common.form.toggle name="is_active" model="form.is_active" label="Aktif" hint="User nonaktif tidak bisa login." class="sm:pt-7"
                    x-bind:class="form.is_self && 'pointer-events-none opacity-60'" />
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="user-form" :create-another="false" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
