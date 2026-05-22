@extends('layouts.app')
@section('title', 'My Profile')

@section('content')
<div style="padding:2rem;">
    <h2>My Profile</h2>
    
    <div class="card" style="max-width:600px;">
        <div class="card-body">
            <p><strong>Name:</strong> {{ $user->name }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Phone:</strong> {{ $user->phone ?? 'Not provided' }}</p>
            <p><strong>Role:</strong> {{ $user->roles->pluck('name')->join(', ') }}</p>
            
            <a href="{{ route('admin.profile.edit') }}" class="btn btn-primary">Edit Profile</a>
        </div>
    </div>
</div>
@endsection