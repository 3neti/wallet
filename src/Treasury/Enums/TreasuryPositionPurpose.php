<?php

declare(strict_types=1);

namespace LBHurtado\Wallet\Treasury\Enums;

enum TreasuryPositionPurpose: string
{
    case TreasuryClearing = 'treasury_clearing';
    case AccountFundingReserve = 'account_funding_reserve';
    case ClientFunds = 'client_funds';
    case PayCodeReserve = 'pay_code_reserve';
    case LegacyUnattributed = 'legacy_unattributed';
    case CommercialClearing = 'commercial_clearing';
    case ProviderCostPayable = 'provider_cost_payable';
    case ProductRevenue = 'product_revenue';
    case PartnerCommissionPayable = 'partner_commission_payable';
    case RoyaltyPayable = 'royalty_payable';
    case TaxPayable = 'tax_payable';
    case CommercialRevenue = 'commercial_revenue';

    public function label(): string
    {
        return match ($this) {
            self::TreasuryClearing => 'Treasury Clearing Position',
            self::AccountFundingReserve => 'Account Funding Reserve Position',
            self::ClientFunds => 'Client Funds Position',
            self::PayCodeReserve => 'Pay Code Reserve Position',
            self::LegacyUnattributed => 'Legacy Unattributed Position',
            self::CommercialClearing => 'Commercial Waterfall Clearing Position',
            self::ProviderCostPayable => 'Provider Cost Payable Position',
            self::ProductRevenue => 'Product Revenue Position',
            self::PartnerCommissionPayable => 'Partner Commission Payable Position',
            self::RoyaltyPayable => 'Royalty Payable Position',
            self::TaxPayable => 'Tax Payable Position',
            self::CommercialRevenue => 'Commercial Revenue Position',
        };
    }
}
