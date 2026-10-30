<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public function save(): void
    {
        $this->dispatch('profile-updated');
    }

    public function render()
    {
        return view('livewire.settings.profile');
    }
}
