@extends('layouts.app')

{{-- Member (panel admin): tambah, ubah, hapus. --}}
@section('content')
    <x-common.page-breadcrumb pageTitle="Member" />

    <x-main.members.manager :members="$members" :route-prefix="$routePrefix" :can-delete="$canDelete" />
@endsection
