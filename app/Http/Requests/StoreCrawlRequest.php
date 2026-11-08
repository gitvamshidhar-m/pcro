<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCrawlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'pages' => ['nullable', 'integer', 'min:5', 'max:'.(int) config('cro.crawl.max_pages')],
            'depth' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('cro.crawl.max_depth')],
            'lighthouse' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');

        if (is_string($url)) {
            $this->merge(['url' => trim($url)]);
        }
    }
}
