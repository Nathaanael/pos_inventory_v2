@use('Illuminate\Support\Js')

{{--
    Daftar + form tambah/ubah Member. Simpan lewat ajaxForm: tabel diperbarui tanpa reload.
    Props:
      members      LengthAwarePaginator<Member>
      routePrefix  "members" (admin) atau "kasir.members" (kasir)
      canDelete    tampilkan aksi Hapus (admin saja)
      layout       "modal" (admin: tabel penuh + modal tambah/ubah)
                   "split" (kasir: form langsung di kiri, tabel di kanan; "Ubah" mengisi form kiri)
--}}
@props(['members', 'routePrefix', 'canDelete' => false, 'layout' => 'modal'])

@php $split = $layout === 'split'; @endphp

<div x-data="crudPage({
        blank: { name: '', phone: '', birth_date: '' },
        routes: { store: @js(route($routePrefix.'.store')), update: @js(route($routePrefix.'.update', '__ID__')) },
        reopen: @js(\App\Support\CrudPage::reopen()),
    })"
    @if ($split)
        {{-- Form selalu tampil: setelah simpan kosongkan & kembali ke mode tambah. --}}
        x-on:ajax-form-success="create(); $nextTick(() => document.getElementById('member-name')?.focus())"
    @else
        x-on:ajax-form-success="saved($event)"
    @endif
    @class(['grid grid-cols-1 items-start gap-6 lg:grid-cols-[380px_minmax(0,1fr)]' => $split])>

    @if ($split)
        {{-- Kasir: form tambah/ubah langsung di halaman --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 lg:sticky lg:top-24 dark:border-gray-800 dark:bg-white/3"
            x-bind:class="isEdit && 'ring-2 ring-brand-500/30'">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90" x-text="isEdit ? 'Ubah Member' : 'Tambah Member'">Tambah Member</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                x-text="isEdit ? 'Perbarui data member lalu simpan.' : 'Isi data pelanggan untuk mendaftarkan member baru.'">
                Isi data pelanggan untuk mendaftarkan member baru.
            </p>

            <form id="member-form" method="POST" x-bind:action="action" class="mt-5 grid grid-cols-1 gap-5"
                x-data="ajaxForm({ regions: ['members-table'] })" x-on:submit.prevent="submit($event)">
                <x-common.crud-form-fields />
                <x-main.members.fields />

                <div class="flex flex-col gap-2">
                    <x-ui.button type="submit" size="lg" class="w-full" x-bind:disabled="busy">
                        <span x-text="busy ? 'Menyimpan…' : (isEdit ? 'Simpan Perubahan' : 'Simpan Member')">Simpan Member</span>
                    </x-ui.button>
                    <x-ui.button type="button" variant="outline" class="w-full" x-show="isEdit" x-cloak x-on:click="create()">
                        Batal Ubah
                    </x-ui.button>
                </div>
            </form>
        </div>
    @endif

    {{-- id="members-table": diperbarui ajaxForm setelah simpan --}}
    <div id="members-table" class="min-w-0">
        <x-common.table-card title="Daftar Member" :paginator="$members" searchPlaceholder="Cari nama / nomor telepon...">
            @unless ($split)
                <x-slot:actions>
                    <x-ui.button x-on:click="create()">
                        <x-svg.plus class="size-5" />
                        Tambah Member
                    </x-ui.button>
                </x-slot:actions>
            @endunless

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-44">Nama Member</th>
                    <th class="table-th min-w-36">Nomor Telepon</th>
                    <th class="table-th min-w-36">Tanggal Lahir</th>
                    @unless ($split)
                        <th class="table-th min-w-32">Terdaftar</th>
                    @endunless
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($members as $member)
                    @php
                        $row = [
                            'id' => $member->id,
                            'name' => $member->name,
                            'phone' => $member->phone,
                            'birth_date' => $member->birth_date->format('Y-m-d'),
                        ];
                        // Kasir: isi form kiri lalu fokus ke kolom nama.
                        $editCall = 'edit('.Js::from($row).')'
                            .($split ? '; $nextTick(() => document.getElementById("member-name")?.focus())' : '');
                    @endphp
                    <tr class="table-row" @if ($split) x-bind:class="isEdit && editId === {{ $member->id }} && 'bg-brand-50/60 dark:bg-brand-500/10'" @endif>
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">
                            {{ $member->name }}
                            @if ($member->birth_date->isBirthday())
                                <x-ui.badge size="sm" color="warning" class="ms-1.5">Ulang tahun hari ini</x-ui.badge>
                            @endif
                        </td>
                        <td class="table-td whitespace-nowrap tabular-nums">{{ $member->formattedPhone() }}</td>
                        <td class="table-td whitespace-nowrap">
                            {{ \App\Support\Format::date($member->birth_date, 'd M Y') }}
                            <span class="text-xs text-gray-400">({{ $member->birth_date->age }} th)</span>
                        </td>
                        @unless ($split)
                            <td class="table-td whitespace-nowrap text-gray-500 dark:text-gray-400">{{ \App\Support\Format::date($member->created_at, 'd M Y') }}</td>
                        @endunless
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="{{ $editCall }}">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                @if ($canDelete)
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Member', message: {{ Js::from('Hapus member '.$member->name.'?') }}, action: {{ Js::from(route('members.destroy', $member)) }} })">
                                        <x-svg.trash-bin /> Hapus
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty :colspan="$split ? 4 : 5" title="Belum ada member"
                        :description="$split ? 'Member yang didaftarkan lewat form di kiri akan muncul di sini.' : 'Tambahkan member dengan nama, nomor telepon, dan tanggal lahir.'" />
                @endforelse
            </tbody>
        </x-common.table-card>
    </div>

    @unless ($split)
        {{-- Admin: modal tambah/ubah --}}
        <x-common.modal show="modalOpen" close="close()" max-width="lg">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Member' : 'Tambah Member'"></span></x-slot:heading>

            <form id="member-form" method="POST" x-bind:action="action" class="grid grid-cols-1 gap-5"
                x-data="ajaxForm({ regions: ['members-table'] })" x-on:submit.prevent="submit($event)">
                <x-common.crud-form-fields />
                <x-main.members.fields />
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="member-form" />
            </x-slot:footer>
        </x-common.modal>
    @endunless
</div>
