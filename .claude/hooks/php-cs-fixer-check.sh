#!/bin/bash

FILE=$(php -r '$d = json_decode(file_get_contents("php://stdin"), true); echo $d["tool_input"]["file_path"] ?? "";')

[[ "$FILE" == *.php ]] || exit 0

OUTPUT=$(docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php "$FILE" 2>&1)
EXIT_CODE=$?

[[ $EXIT_CODE -eq 0 ]] && exit 0

php -r '
$output = $argv[1];
$file = $argv[2];
echo json_encode([
    "hookSpecificOutput" => [
        "hookEventName" => "PostToolUse",
        "additionalContext" => "php-cs-fixer found code style violations in $file. Fix them:\n$output",
    ],
]) . "\n";
' -- "$OUTPUT" "$FILE"
