<?php

namespace App\Enums;

enum RequestType: string
{
    case Promotion = 'promotion';
    case Donation = 'donation';
    case ProductDemonstration = 'product_demonstration';
    case CustomerHcpEngagement = 'customer_hcp_engagement';
    case Tender = 'tender';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Promotion => 'Promotion',
            self::Donation => 'Donation',
            self::ProductDemonstration => 'Product demonstration',
            self::CustomerHcpEngagement => 'Customer/healthcare professional engagement',
            self::Tender => 'Tender',
            self::Other => 'Other',
        };
    }
}
