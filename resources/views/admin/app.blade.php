@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div id="vue-app" class="admin-app">
    <!-- Vue Router View -->
    <component 
        :is="currentComponent"
        :initial-data="{{ json_encode($data ?? []) }}"
    ></component>
</div>

<script>
    window.currentView = '{{ $view ?? 'admin-dashboard' }}';
</script>
@endsection