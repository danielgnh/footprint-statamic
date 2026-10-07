<?php

use Symfony\Component\Yaml\Yaml;

$skills = __DIR__.'/../../resources/boost/skills';

it('gives every skill the frontmatter Boost requires', function (string $directory) {
    $skill = (string) file_get_contents("{$directory}/SKILL.md");

    preg_match('/\A---\n(.*?)\n---\n/s', $skill, $match);
    $frontmatter = Yaml::parse($match[1] ?? '');

    expect($frontmatter['name'] ?? null)->toBe(basename($directory))
        ->and($frontmatter['description'] ?? '')->not->toBeEmpty();

    preg_match_all('/`(references\/[\w.-]+\.md)`/', $skill, $links);

    foreach (array_unique($links[1]) as $link) {
        expect("{$directory}/{$link}")->toBeFile();
    }
})->with(fn () => glob("{$skills}/*", GLOB_ONLYDIR) ?: []);

it('only documents PHP files that parse', function () use ($skills) {
    $blocks = collect([...glob("{$skills}/*/*.md") ?: [], ...glob("{$skills}/*/references/*.md") ?: []])
        ->flatMap(function (string $file): array {
            preg_match_all('/```php\n(<\?php.*?)```/s', (string) file_get_contents($file), $blocks);

            return $blocks[1];
        });

    expect($blocks)->not->toBeEmpty();

    $blocks->each(fn (string $code) => expect(fn () => PhpToken::tokenize($code, TOKEN_PARSE))->not->toThrow(ParseError::class));
});
