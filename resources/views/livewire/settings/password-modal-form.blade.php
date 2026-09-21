<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<form wire:submit="updatePassword" class="form-grid" style="gap:14px">
    <label>{{ __('Current password') }}
        <input wire:model="current_password" type="password" name="current_password" required autocomplete="current-password">
        @error('current_password') <small style="color:#af3b2c">{{ $message }}</small> @enderror
    </label>

    <label>{{ __('New password') }}
        <input wire:model="password" type="password" name="password" required autocomplete="new-password">
        @error('password') <small style="color:#af3b2c">{{ $message }}</small> @enderror
    </label>

    <label>{{ __('Confirm password') }}
        <input wire:model="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
    </label>

    <button type="submit" class="save-button" style="margin:0">{{ __('Save') }}</button>

    <p class="save-notice" x-data="{ shown: false }" x-on:password-updated.window="shown = true; setTimeout(() => shown = false, 2000)" x-show="shown" x-cloak>{{ __('Saved.') }}</p>
</form>
