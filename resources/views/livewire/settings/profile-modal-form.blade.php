<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }
}; ?>

<form wire:submit="updateProfileInformation" class="form-grid" style="gap:14px">
    <label>{{ __('Name') }}
        <input wire:model="name" type="text" name="name" required autofocus autocomplete="name">
        @error('name') <small style="color:#af3b2c">{{ $message }}</small> @enderror
    </label>

    <label>{{ __('Email') }}
        <input wire:model="email" type="email" name="email" required autocomplete="email">
        @error('email') <small style="color:#af3b2c">{{ $message }}</small> @enderror
    </label>

    <button type="submit" class="save-button" style="margin:0">{{ __('Save') }}</button>

    <p class="save-notice" x-data="{ shown: false }" x-on:profile-updated.window="shown = true; setTimeout(() => shown = false, 2000)" x-show="shown" x-cloak>{{ __('Saved.') }}</p>
</form>
