<?php
declare(strict_types=1);

namespace SiteForge;

/** What a generator hands back after building or editing a site. */
final class Result
{
    /**
     * @param array<string,string> $files relative path => contents
     * @param array|null           $spec  structured site description (offline builder only)
     */
    public function __construct(
        public array $files,
        public string $summary,
        public ?array $spec = null,
    ) {
    }
}
