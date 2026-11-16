{{--
    Input tersembunyi wajib untuk form modal crudPage(): CSRF, method (POST/PUT), mode & id
    (agar modal bisa dibuka ulang dengan benar saat validasi gagal).
--}}
@csrf
<input type="hidden" name="_method" x-bind:value="isEdit ? 'PUT' : 'POST'">
<input type="hidden" name="_mode" x-bind:value="mode">
<input type="hidden" name="_id" x-bind:value="editId ?? ''">
