<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $name = '';
    public string $email = '';
    public $photo;
    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'photo' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($this->photo) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $validated['profile_photo_path'] = $this->photo->store('profile-photos', 'public');
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            if (array_key_exists('email', $validated) && $user->isDirty('email')) {
                $user->email_verified_at = null;
                $user->sendEmailVerificationNotification();
            }

            if ($user->role === 'Mahasiswa' && $user->mahasiswa) {
                $user->mahasiswa->update(['nama_mahasiswa' => $validated['name']]);
            } elseif (in_array($user->role, ['Dosen Pembimbing', 'Dosen Komisi']) && $user->dosen) {
                $user->dosen->update(['nama_dosen' => $validated['name']]);
            }
        });

        $this->dispatch('profile-updated', name: $user->name);
        $this->photo = null;

        // TAMBAHKAN BARIS INI untuk me-refresh halaman
        $this->redirect(route('settings.profile'), navigate: true);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Perbarui informasi profil dan alamat email akun Anda.')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            {{-- Form Upload Foto Profil BARU --}}
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <flux:label for="photo">Foto Profil</flux:label>

                <div class="mt-2 flex items-center">
                    @if (auth()->user()->profile_photo_path)
                        <img src="{{ asset('storage/' . auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()->name }}" class="h-20 w-20 rounded-full object-cover" x-show="!photoPreview">
                    @else
                        <span class="inline-block h-20 w-20 overflow-hidden rounded-full bg-gray-100" x-show="!photoPreview">
                            <svg class="h-full w-full text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 20.993V24H0v-2.993A1 1 0 001 19.007v-3.006a1 1 0 001-1v-4.005a1 1 0 011-1h16a1 1 0 011 1v4.005a1 1 0 001 1v3.006a1 1 0 001 1zM12 11a4 4 0 100-8 4 4 0 000 8z" />
                            </svg>
                        </span>
                    @endif

                    <div class="h-20 w-20 rounded-full" x-show="photoPreview" style="display: none;">
                        <span class="block h-full w-full rounded-full bg-cover bg-center bg-no-repeat"
                              x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                        </span>
                    </div>

                    <input type="file" id="photo" class="hidden"
                           wire:model.live="photo"
                           x-ref="photo"
                           x-on:change="
                                        photoName = $refs.photo.files[0].name;
                                        const reader = new FileReader();
                                        reader.onload = (e) => {
                                            photoPreview = e.target.result;
                                        };
                                        reader.readAsDataURL($refs.photo.files[0]);
                                " />

                    <flux:button type="button" class="ms-5" x-on:click.prevent="$refs.photo.click()">
                        {{ __('Pilih Foto Baru') }}
                    </flux:button>
                </div>
                @error('photo') <span class="text-sm text-red-500 mt-2">{{ $message }}</span> @enderror
            </div>

        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

{{--        <livewire:settings.delete-user-form />--}}
    </x-settings.layout>
</section>
