<?php

namespace ReelMaker\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $template = $this->route('template');

        return [
            'images' => ['required', 'array', 'min:'.$template->min_images, 'max:'.$template->max_images],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('reel-maker.max_image_kb')],
            'caption' => ['nullable', 'string', 'max:120'],
            'track_id' => ['nullable', 'integer', Rule::exists('reel_tracks', 'id')->where('is_active', true)],
        ];
    }

    public function attributes(): array
    {
        return ['images' => 'تصاویر', 'images.*' => 'تصویر', 'caption' => 'متن', 'track_id' => 'آهنگ'];
    }
}
