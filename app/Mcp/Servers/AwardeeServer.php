<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListAwardsTool;
use App\Mcp\Tools\ListDecreeAwardeesTool;
use App\Mcp\Tools\SearchAwardeesTool;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Awardee Registry')]
#[Version('1.3.0')]
#[Instructions('Provides data about people awarded with Ukrainian state awards by presidential decrees. Names are stored in Ukrainian: the genitive form is kept exactly as written in the decree, and the nominative form is available when known. A person awarded several times has a separate record per award.

Turn the wording of the user into tool arguments:
- a decree number: "указ № 1025", "указ No 1025/2026" -> decree_number "1025/2026"; "в останньому указі", "в найновішому указі" -> decree_number "latest"; "в останньому указі, де є орден за мужність" -> decree_number "latest" plus award "орден за мужність" (the newest decree that contains it is used);
- parts of a person name -> query;
- a concrete award -> award, for example "орден за мужність", "медаль за військову службу", "орден Богдана Хмельницького ІІІ ступеня". Do not pass generic wording such as "державні нагороди", "відзнаки", "нагороди" as award;
- the posthumous status -> is_posthumous.

Choose the tool by the question:
- "чи є в указі 1025 нагороджені орденом за мужність" -> list_decree_awardees with decree_number and award;
- "якими нагородами нагороджували в указі 1131/2026", "які відзнаки в останньому указі" -> list_decree_awardees with decree_number (or "latest") and per_page 1: the "awards" breakdown of the response already lists every award of the decree with its count and the posthumous part, so never call the tool once per award;
- "виведи всіх нагороджених указом 1131/2026" -> list_decree_awardees with decree_number, page by page while last_page is not reached;
- "виведи всіх нагороджених орденом за мужність" -> search_awardees with award only, page by page while last_page is not reached;
- "чи був нагороджений Петренко Сергій" -> search_awardees with query only.

Use list_awards when the award wording may be ambiguous or when the user asks which awards exist in the whole registry; the "awards" breakdown of list_decree_awardees is the decree-scoped counterpart and is only present when no award filter is given. A record is one award, not one person, so count distinct people yourself when the answer must be about people. Present the results to the user as a table and answer the question directly (yes/no with counts, and the decree number when the decree was resolved as the latest one) before the table.

The award summary tables ("awards" of list_decree_awardees and the rows of list_awards) mirror the awards table of the user panel and must be reproduced as they are: two columns, "Назва нагороди" (the award title) and "Нагороджені" (the total number of awardees), where the posthumous part is written inside the brackets of the same cell, for example "280 (184 посмертно)". Each row also carries awardees_label with that cell already built ("280 (184 посмертно)"), so the label can be used verbatim. Never split the total and the posthumous count into two separate columns, never add a percentage, a "Разом" row or any other derived column, and keep the order of the rows as returned.')]
class AwardeeServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SearchAwardeesTool::class,
        ListDecreeAwardeesTool::class,
        ListAwardsTool::class,
    ];

    protected function cacheHints(): array
    {
        $hints = [];

        $toolsListTtl = (int) config('mcp.cache_hints.tools_list_ttl_ms', 30000);
        if ($toolsListTtl > 0) {
            $hints['tools/list'] = new Cacheable(
                ttlMs: $toolsListTtl,
                scope: CacheScope::Public,
            );
        }

        $discoverTtl = (int) config('mcp.cache_hints.server_discover_ttl_ms', 60000);
        if ($discoverTtl > 0) {
            $hints['server/discover'] = new Cacheable(
                ttlMs: $discoverTtl,
                scope: CacheScope::Public,
            );
        }

        return $hints;
    }
}
