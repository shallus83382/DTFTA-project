@extends('layouts.app')

@section('title', 'DTFTA CRM - Product Categories')
@section('page-title', 'Product Categories')

@section('content')
<div class="category-page">
    @if(session('success'))
        <div class="category-message category-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="category-message category-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php
        $editingCategory = old('form_mode') === 'edit'
            ? $categories->firstWhere('id', (int) old('category_id'))
            : null;
        $modalMode = $editingCategory ? 'edit' : 'add';
        $modalShouldOpen = $errors->any() && (old('form_mode') === 'add' || $editingCategory);
        $modalImageDisk = $editingCategory && str_starts_with($editingCategory->image_path, 'product-category/')
            ? 's3'
            : 'public';
    @endphp

    <section class="category-panel">
        <div class="category-table-toolbar">
            <div>
                <h2>Product categories</h2>
                <p>Manage category names and images.</p>
            </div>
            <button type="button" class="btn-primary" id="open-add-category">Add Category</button>
        </div>
        <div class="category-table-wrap">
            <table class="category-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category name</th>
                        <th>Category image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        @php($imageDisk = str_starts_with($category->image_path, 'product-category/') ? 's3' : 'public')
                        <tr>
                            <td>{{ $category->id }}</td>
                            <td>{{ $category->name }}</td>
                            <td>
                                <img class="category-table-image" src="{{ Storage::disk($imageDisk)->url($category->image_path) }}" alt="{{ $category->name }}">
                            </td>
                            <td>
                                <div class="category-actions">
                                    <button
                                        type="button"
                                        class="btn-secondary edit-category-button"
                                        data-category-id="{{ $category->id }}"
                                        data-category-name="{{ $category->name }}"
                                        data-category-image="{{ Storage::disk($imageDisk)->url($category->image_path) }}"
                                        data-update-url="{{ route('crm.product-categories.update', $category) }}"
                                    >Edit Category</button>
                                    <form method="POST" action="{{ route('crm.product-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Products already assigned to it will keep their category text.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Delete Category</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="category-empty">No product categories have been added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="category-modal" id="category-modal" @if(!$modalShouldOpen) hidden @endif>
    <div class="category-modal-backdrop" data-close-category-modal></div>
    <section class="category-modal-card" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
        <div class="category-modal-heading">
            <h2 id="category-modal-title">{{ $editingCategory ? 'Edit Category' : 'Add a category' }}</h2>
            <button type="button" class="category-modal-close" aria-label="Close dialog" data-close-category-modal>&times;</button>
        </div>
        <form
            method="POST"
            action="{{ $editingCategory ? route('crm.product-categories.update', $editingCategory) : route('crm.product-categories.store') }}"
            enctype="multipart/form-data"
            class="category-form"
            id="category-modal-form"
        >
            @csrf
            <input type="hidden" name="_method" value="PUT" id="category-method" @if(!$editingCategory) disabled @endif>
            <input type="hidden" name="form_mode" value="{{ $modalMode }}" id="category-form-mode">
            <input type="hidden" name="category_id" value="{{ $editingCategory?->id }}" id="category-id">
            <label>
                Category name
                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $editingCategory?->name ?? '') }}"
                    maxlength="150"
                    required
                    placeholder="e.g. Bottles"
                    id="category-name"
                >
            </label>
            <label>
                <span id="category-image-label">{{ $editingCategory ? 'Replace image (optional)' : 'Category image' }}</span>
                <input
                    type="file"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    class="category-image-input"
                    data-preview-target="category-modal-image-preview"
                    id="category-image"
                    @if(!$editingCategory) required @endif
                >
                <small>JPG, PNG, or WebP; up to 5 MB.</small>
            </label>
            <img
                id="category-modal-image-preview"
                class="category-image-preview"
                @if($editingCategory)
                    src="{{ Storage::disk($modalImageDisk)->url($editingCategory->image_path) }}"
                    alt="{{ $editingCategory->name }} category image preview"
                @else
                    alt="Selected category image preview"
                    hidden
                @endif
            >
            <div class="category-modal-actions">
                <button type="button" class="btn-secondary" data-close-category-modal>Cancel</button>
                <button type="submit" class="btn-primary" id="category-submit-button">{{ $editingCategory ? 'Save Changes' : 'Add Category' }}</button>
            </div>
        </form>
    </section>
</div>
@endsection

@push('styles')
<style>
.category-page { display: grid; gap: 18px; }
.category-panel { padding: 22px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; }
.category-panel h2 { margin: 0 0 8px; color: var(--text-primary); }
.category-panel p, .category-form small { color: var(--text-muted); }
.category-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.category-table-toolbar p { margin: 0; }
.category-table-wrap { overflow-x: auto; }
.category-table { width: 100%; border-collapse: collapse; color: var(--text-primary); }
.category-table th, .category-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle; }
.category-table th { color: var(--text-secondary); font-size: 13px; }
.category-table-image { display: block; width: 88px; height: 82px; object-fit: contain; border-radius: 8px; background: var(--bg-primary); }
.category-actions { display: flex; align-items: center; gap: 8px; }
.category-actions form { margin: 0; }
.category-empty { color: var(--text-muted); text-align: center !important; }
.category-form { display: grid; gap: 14px; margin-top: 16px; }
.category-form label { display: grid; gap: 6px; color: var(--text-secondary); font-size: 13px; font-weight: 600; }
.category-form input { width: 100%; padding: 10px 12px; color: var(--text-primary); background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 8px; }
.category-form input[type="file"] { padding: 8px; }
.category-image-preview { display: block; width: 176px; height: 166px; max-width: 100%; object-fit: contain; border-radius: 8px; background: var(--bg-primary); }
.category-image-preview[hidden] { display: none; }
.category-message { padding: 12px 16px; border-radius: 8px; }
.category-success { color: #166534; background: #dcfce7; }
.category-error { color: #991b1b; background: #fee2e2; }
.category-modal { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.category-modal[hidden] { display: none; }
.category-modal-backdrop { position: absolute; inset: 0; background: rgba(2, 6, 23, 0.72); }
.category-modal-card { position: relative; z-index: 1; width: min(100%, 520px); max-height: calc(100vh - 40px); overflow-y: auto; padding: 22px; border: 1px solid var(--border-color); border-radius: 14px; background: var(--bg-card); box-shadow: 0 24px 70px rgba(0, 0, 0, 0.4); }
.category-modal-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.category-modal-heading h2 { margin: 0; color: var(--text-primary); }
.category-modal-close { padding: 2px 8px; border: 0; color: var(--text-secondary); background: transparent; font-size: 28px; cursor: pointer; }
.category-modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
@media (max-width: 640px) {
    .category-panel { padding: 14px; }
    .category-table-toolbar { align-items: flex-start; }
    .category-actions { align-items: flex-start; flex-direction: column; }
    .category-table th, .category-table td { padding: 10px 8px; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('category-modal');
    const form = document.getElementById('category-modal-form');
    const nameInput = document.getElementById('category-name');
    const imageInput = document.getElementById('category-image');
    const imagePreview = document.getElementById('category-modal-image-preview');
    const methodInput = document.getElementById('category-method');
    const formModeInput = document.getElementById('category-form-mode');
    const categoryIdInput = document.getElementById('category-id');
    const title = document.getElementById('category-modal-title');
    const imageLabel = document.getElementById('category-image-label');
    const submitButton = document.getElementById('category-submit-button');

    if (!modal || !form) return;

    function openModal(mode, category) {
        const editing = mode === 'edit';
        form.action = editing ? category.updateUrl : '{{ route('crm.product-categories.store') }}';
        formModeInput.value = mode;
        categoryIdInput.value = editing ? category.id : '';
        methodInput.disabled = !editing;
        nameInput.value = editing ? category.name : '';
        imageInput.required = !editing;
        imageInput.value = '';
        title.textContent = editing ? 'Edit Category' : 'Add a category';
        imageLabel.textContent = editing ? 'Replace image (optional)' : 'Category image';
        submitButton.textContent = editing ? 'Save Changes' : 'Add Category';

        if (imagePreview.dataset.objectUrl) {
            URL.revokeObjectURL(imagePreview.dataset.objectUrl);
            delete imagePreview.dataset.objectUrl;
        }
        if (editing && category.imageUrl) {
            imagePreview.src = category.imageUrl;
            imagePreview.alt = category.name + ' category image preview';
            imagePreview.hidden = false;
        } else {
            imagePreview.removeAttribute('src');
            imagePreview.alt = 'Selected category image preview';
            imagePreview.hidden = true;
        }

        modal.hidden = false;
        nameInput.focus();
    }

    function closeModal() {
        modal.hidden = true;
    }

    document.getElementById('open-add-category').addEventListener('click', function() {
        openModal('add', {});
    });

    document.querySelectorAll('.edit-category-button').forEach(function(button) {
        button.addEventListener('click', function() {
            openModal('edit', {
                id: button.dataset.categoryId,
                name: button.dataset.categoryName,
                imageUrl: button.dataset.categoryImage,
                updateUrl: button.dataset.updateUrl
            });
        });
    });

    modal.querySelectorAll('[data-close-category-modal]').forEach(function(button) {
        button.addEventListener('click', closeModal);
    });
    modal.addEventListener('click', function(event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    imageInput.addEventListener('change', function() {
        const file = imageInput.files && imageInput.files[0];
        if (!file) return;

        if (imagePreview.dataset.objectUrl) URL.revokeObjectURL(imagePreview.dataset.objectUrl);
        const objectUrl = URL.createObjectURL(file);
        imagePreview.dataset.objectUrl = objectUrl;
        imagePreview.src = objectUrl;
        imagePreview.hidden = false;
    });

    if (!modal.hidden) nameInput.focus();
});
</script>
@endpush
