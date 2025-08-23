<div class="tree-node" data-group-id="{{ $group->id }}" style="margin-left: {{ ($group->level ?? 0) * 2 }}rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: white; border: 1px solid var(--border); border-radius: 0.5rem;">
        <div style="display: flex; align-items: center;">
            @if($group->children->count() > 0)
            <button onclick="toggleGroup('{{ $group->id }}')" style="background: none; border: none; cursor: pointer; margin-right: 0.5rem;">
                ▼
            </button>
            @endif
            <div style="width: 20px; height: 20px; background: {{ $group->color }}; border-radius: 50%; margin-right: 0.75rem;"></div>
            <div>
                <div style="font-weight: 600;">{{ $group->name }}</div>
                <div style="font-size: 0.875rem; color: #718096;">{{ $group->description }}</div>
                <div style="margin-top: 0.25rem;">
                    @foreach($group->permissions as $permission)
                        <span class="badge badge-primary" style="font-size: 0.7rem;">{{ $permission->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="btn-group">
            <button class="btn btn-sm">Bearbeiten</button>
            <button class="btn btn-sm btn-danger">Löschen</button>
        </div>
    </div>
</div>

@if($group->children->count() > 0)
<div data-group-parent="{{ $group->id }}" style="margin-left: 1rem;">
    @foreach($group->children as $child)
        @include('admin.partials.group-node', ['group' => $child])
    @endforeach
</div>
@endif