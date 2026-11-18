@props(['name', 'label', 'required' => false, 'wide' => false])
<div @class(['profile-field', 'profile-field-wide' => $wide])>
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    {{ $slot }}
    @error($name)<p id="{{ $name }}-error" class="profile-field-error">{{ $message }}</p>@enderror
</div>
