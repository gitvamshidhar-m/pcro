<?php

namespace App\Enums;

enum PageType: string
{
    case Home = 'home';
    case Product = 'product';
    case Service = 'service';
    case Pricing = 'pricing';
    case Contact = 'contact';
    case About = 'about';
    case Blog = 'blog';
    case Landing = 'landing';
    case Legal = 'legal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Homepage',
            self::Product => 'Product',
            self::Service => 'Service',
            self::Pricing => 'Pricing',
            self::Contact => 'Contact',
            self::About => 'About',
            self::Blog => 'Blog',
            self::Landing => 'Landing page',
            self::Legal => 'Legal',
            self::Other => 'Other',
        };
    }

    /**
     * Page types that matter most for conversion analysis.
     *
     * @return array<int, self>
     */
    public static function conversionCritical(): array
    {
        return [self::Home, self::Product, self::Service, self::Pricing, self::Contact, self::Landing];
    }
}
