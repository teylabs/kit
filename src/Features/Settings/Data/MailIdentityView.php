<?php

namespace Tey\Kit\Features\Settings\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A workspace's mail identity, its custom domain's status and the address mail actually goes from right now. */
#[TypeScript]
final class MailIdentityView extends Data
{
    /**
     * @param  list<array{type: string, name: string, value: string, priority: ?int, status: string}>  $dns_records
     */
    public function __construct(
        public int $workspace_id,
        public ?string $sender_name,
        public string $sender_name_default,
        #[LiteralTypeScriptType("'platform' | 'domain'")]
        public string $from_mode,
        public ?string $domain,
        public string $from_local,
        #[LiteralTypeScriptType("'none' | 'pending' | 'verified' | 'failed'")]
        public string $domain_status,
        public array $dns_records,
        public ?string $checked_at,
        #[LiteralTypeScriptType("'member' | 'fixed' | 'custom'")]
        public string $reply_to_mode,
        public ?string $reply_to_address,
        public string $platform_address,
        public ?string $fixed_reply_to,
        public string $effective_from,
        public int $version,
    ) {}
}
