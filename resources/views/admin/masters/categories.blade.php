@extends('layouts.app')
@section('title', 'Category Master')
@section('page-title', 'Category Master')

@section('content')
<div class="grid-2">
    {{-- CREATE CATEGORY --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-plus-circle" style="margin-right:.5rem;color:#2d6a4f;"></i> Add Category</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <div class="form-group">
                    <label>Category Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Home Décor">
                    @error('name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Parent Category</label>
                    <select name="parent_id">
                        <option value="">— None (Top Level) —</option>
                        @foreach($categories->whereNull('parent_id') as $cat)
                            <option value="{{ $cat->id }}" {{ old('parent_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Optional description...">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Add Category</button>
            </form>
        </div>
    </div>

    {{-- CATEGORY LIST --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tags" style="margin-right:.5rem;color:#e8a838;"></i> All Categories ({{ $categories->count() }})</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Category</th><th>Parent</th><th>Products</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div style="width:32px;height:32px;border-radius:8px;background:{{ $cat->parent_id?'#f1f5f9':'#dbeafe' }};display:flex;align-items:center;justify-content:center;font-size:.7rem;">
                                    @if($cat->parent_id)<i class="fas fa-level-up-alt fa-rotate-90" style="color:#94a3b8;"></i>@else<i class="fas fa-folder" style="color:#1e40af;"></i>@endif
                                </div>
                                <div>
                                    <div style="font-weight:600;color:#0d1b2a;font-size:.85rem;">{{ $cat->name }}</div>
                                    @if($cat->description)<div style="font-size:.68rem;color:#94a3b8;">{{ Str::limit($cat->description, 40) }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td style="font-size:.82rem;color:#64748b;">{{ $cat->parent?->name ?? '—' }}</td>
                        <td><span style="font-size:.82rem;font-weight:600;">{{ $cat->products()->count() }}</span></td>
                        <td><span class="badge {{ $cat->is_active?'badge-success':'badge-gray' }}">{{ $cat->is_active?'Active':'Inactive' }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
