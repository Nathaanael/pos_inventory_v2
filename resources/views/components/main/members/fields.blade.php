{{--
    Isian form Member (nama, nomor telepon, tanggal lahir). Dipakai di dalam scope crudPage()
    (x-model ke form.*), baik di modal (admin) maupun form langsung (kasir).
--}}
@props([])

<x-common.form.field label="Nama Member" for="member-name" error="name" required>
    <x-ui.input id="member-name" name="name" x-model="form.name" maxlength="100" placeholder="mis. Budi Santoso" required autocomplete="off" />
</x-common.form.field>

<x-common.form.field label="Nomor Telepon" for="member-phone" error="phone" required hint="Nomor WhatsApp / HP, mis. 0812 3456 7890.">
    <x-ui.input id="member-phone" type="tel" name="phone" x-model="form.phone" maxlength="20" inputmode="tel" placeholder="08xx xxxx xxxx" required autocomplete="off" />
</x-common.form.field>

<x-common.form.field label="Tanggal Lahir" for="member-birth-date" error="birth_date" required>
    <x-ui.input id="member-birth-date" type="date" name="birth_date" x-model="form.birth_date" max="{{ now()->format('Y-m-d') }}" required />
</x-common.form.field>
