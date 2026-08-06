<?php
declare(strict_types=1);

/**
 * The builder: sites you assemble by clicking rather than generating.
 *
 * A site has pages. A page either holds plain content, or lists a collection.
 * A collection ("Games") has fields ("Title", "Cover", "Rating") and entries —
 * so "add games that are out right now and give them a rating" is a collection
 * with four fields, and the add-form and listing page come for free.
 */

require_once __DIR__ . '/projects.php';

/** The field types someone can pick, in the order they're offered. */
function field_types(): array
{
    return [
        'text'     => ['label' => 'Short text',   'hint' => 'A title, a name, a place'],
        'longtext' => ['label' => 'Long text',    'hint' => 'A description or review'],
        'rating'   => ['label' => 'Rating',       'hint' => 'Stars out of five'],
        'number'   => ['label' => 'Number',       'hint' => 'A price, a score, a year'],
        'image'    => ['label' => 'Image',        'hint' => 'A cover or photo'],
        'date'     => ['label' => 'Date',         'hint' => 'A release or event date'],
        'choice'   => ['label' => 'Choice',       'hint' => 'Pick one from a list you set'],
        'url'      => ['label' => 'Link',         'hint' => 'A web address'],
        'checkbox' => ['label' => 'Yes / no',     'hint' => 'A simple tick box'],
    ];
}

function field_type_label(string $type): string
{
    return field_types()[$type]['label'] ?? ucfirst($type);
}

// --- Collections ------------------------------------------------------------

function collection_create(int $projectId, string $name, string $singular = ''): int
{
    $name = trim($name) !== '' ? trim($name) : 'Items';
    $singular = trim($singular) !== '' ? trim($singular) : rtrim($name, 's');

    $position = (int) db()->query(
        'SELECT COALESCE(MAX(position), 0) + 1 FROM collections WHERE project_id = ' . $projectId
    )->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO collections (project_id, name, singular, slug, position) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$projectId, $name, $singular, slugify($name, 'items'), $position]);

    return (int) db()->lastInsertId();
}

function collections_for(int $projectId): array
{
    $stmt = db()->prepare('SELECT * FROM collections WHERE project_id = ? ORDER BY position, id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

/** Fetch one collection, checking it belongs to the given project. */
function collection_get(int $id, ?int $projectId = null): ?array
{
    $sql = 'SELECT * FROM collections WHERE id = ?';
    $args = [$id];
    if ($projectId !== null) {
        $sql .= ' AND project_id = ?';
        $args[] = $projectId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($args);

    return $stmt->fetch() ?: null;
}

function collection_by_slug(int $projectId, string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM collections WHERE project_id = ? AND slug = ?');
    $stmt->execute([$projectId, $slug]);
    return $stmt->fetch() ?: null;
}

function collection_update(int $id, string $name, string $singular, string $sortField, string $sortDir): void
{
    $stmt = db()->prepare(
        'UPDATE collections SET name = ?, singular = ?, slug = ?, sort_field = ?, sort_dir = ? WHERE id = ?'
    );
    $stmt->execute([
        trim($name), trim($singular), slugify($name, 'items'),
        $sortField, $sortDir === 'asc' ? 'asc' : 'desc', $id,
    ]);
}

function collection_delete(int $id): void
{
    db()->prepare('DELETE FROM collections WHERE id = ?')->execute([$id]);
}

// --- Fields -----------------------------------------------------------------

function field_create(int $collectionId, string $label, string $type, string $options = '', bool $required = false): int
{
    $label = trim($label) !== '' ? trim($label) : 'Field';
    if (!array_key_exists($type, field_types())) {
        $type = 'text';
    }

    $position = (int) db()->query(
        'SELECT COALESCE(MAX(position), 0) + 1 FROM fields WHERE collection_id = ' . $collectionId
    )->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO fields (collection_id, label, key, type, options, required, position)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $collectionId, $label, field_unique_key($collectionId, $label),
        $type, trim($options), $required ? 1 : 0, $position,
    ]);

    return (int) db()->lastInsertId();
}

/** Field keys address the JSON blob, so they must be unique within a collection. */
function field_unique_key(int $collectionId, string $label): string
{
    $base = str_replace('-', '_', slugify($label, 'field'));

    $stmt = db()->prepare('SELECT key FROM fields WHERE collection_id = ?');
    $stmt->execute([$collectionId]);
    $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $key = $base;
    $n = 2;
    while (in_array($key, $taken, true)) {
        $key = $base . '_' . $n++;
    }

    return $key;
}

function fields_for(int $collectionId): array
{
    $stmt = db()->prepare('SELECT * FROM fields WHERE collection_id = ? ORDER BY position, id');
    $stmt->execute([$collectionId]);
    return $stmt->fetchAll();
}

function field_get(int $id, int $collectionId): ?array
{
    $stmt = db()->prepare('SELECT * FROM fields WHERE id = ? AND collection_id = ?');
    $stmt->execute([$id, $collectionId]);
    return $stmt->fetch() ?: null;
}

function field_delete(int $id, int $collectionId): void
{
    db()->prepare('DELETE FROM fields WHERE id = ? AND collection_id = ?')->execute([$id, $collectionId]);
}

function field_move(int $id, int $collectionId, int $direction): void
{
    $fields = fields_for($collectionId);
    $index = null;
    foreach ($fields as $i => $field) {
        if ((int) $field['id'] === $id) {
            $index = $i;
            break;
        }
    }
    $swap = $index === null ? null : $index + ($direction < 0 ? -1 : 1);
    if ($index === null || $swap === null || !isset($fields[$swap])) {
        return;
    }

    $stmt = db()->prepare('UPDATE fields SET position = ? WHERE id = ?');
    $stmt->execute([$swap, $fields[$index]['id']]);
    $stmt->execute([$index, $fields[$swap]['id']]);
}

// --- Entries ----------------------------------------------------------------

/**
 * Validate and clean submitted values against a collection's fields.
 *
 * @return array{0:array<string,mixed>,1:array<int,string>} cleaned data, errors
 */
function entry_clean(array $fields, array $input, array $uploads = []): array
{
    $data = [];
    $errors = [];

    foreach ($fields as $field) {
        $key = (string) $field['key'];
        $raw = $input[$key] ?? '';
        $label = (string) $field['label'];

        switch ($field['type']) {
            case 'rating':
                $value = (int) $raw;
                $data[$key] = $value >= 1 && $value <= 5 ? $value : null;
                break;

            case 'number':
                $raw = trim((string) $raw);
                if ($raw === '') {
                    $data[$key] = null;
                } elseif (!is_numeric($raw)) {
                    $errors[] = $label . ' needs to be a number.';
                } else {
                    $data[$key] = 0 + $raw;
                }
                break;

            case 'checkbox':
                $data[$key] = !empty($raw);
                break;

            case 'date':
                $raw = trim((string) $raw);
                $data[$key] = $raw !== '' ? $raw : null;
                break;

            case 'url':
                $raw = trim((string) $raw);
                if ($raw !== '' && !filter_var($raw, FILTER_VALIDATE_URL)) {
                    $errors[] = $label . ' needs to be a full web address, starting with https://';
                } else {
                    $data[$key] = $raw !== '' ? $raw : null;
                }
                break;

            case 'image':
                // Keep whatever is already stored unless a new file arrived.
                $data[$key] = $uploads[$key] ?? (trim((string) $raw) !== '' ? trim((string) $raw) : null);
                break;

            case 'longtext':
                $data[$key] = trim((string) $raw) !== '' ? mb_substr(trim((string) $raw), 0, 4000) : null;
                break;

            default:
                $data[$key] = trim((string) $raw) !== '' ? mb_substr(trim((string) $raw), 0, 300) : null;
        }

        if (!empty($field['required'])) {
            $missing = $data[$key] === null || $data[$key] === '' || $data[$key] === false;
            if ($missing) {
                $errors[] = $label . ' is required.';
            }
        }
    }

    return [$data, $errors];
}

function entry_create(int $collectionId, array $data): int
{
    $stmt = db()->prepare('INSERT INTO entries (collection_id, data) VALUES (?, ?)');
    $stmt->execute([$collectionId, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

    return (int) db()->lastInsertId();
}

function entry_update(int $id, int $collectionId, array $data): void
{
    $stmt = db()->prepare(
        "UPDATE entries SET data = ?, updated_at = datetime('now') WHERE id = ? AND collection_id = ?"
    );
    $stmt->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id, $collectionId]);
}

function entry_delete(int $id, int $collectionId): void
{
    db()->prepare('DELETE FROM entries WHERE id = ? AND collection_id = ?')->execute([$id, $collectionId]);
}

function entry_get(int $id, int $collectionId): ?array
{
    $stmt = db()->prepare('SELECT * FROM entries WHERE id = ? AND collection_id = ?');
    $stmt->execute([$id, $collectionId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['values'] = json_decode((string) $row['data'], true) ?: [];

    return $row;
}

/**
 * All entries in a collection, sorted by the collection's chosen field.
 * Sorting happens in PHP so it works the same for every field type.
 */
function entries_for(int $collectionId, ?array $collection = null): array
{
    $stmt = db()->prepare('SELECT * FROM entries WHERE collection_id = ? ORDER BY id DESC');
    $stmt->execute([$collectionId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['values'] = json_decode((string) $row['data'], true) ?: [];
    }
    unset($row);

    $sortField = (string) ($collection['sort_field'] ?? '');
    if ($sortField === '') {
        return $rows;
    }

    $dir = ($collection['sort_dir'] ?? 'desc') === 'asc' ? 1 : -1;
    usort($rows, static function (array $a, array $b) use ($sortField, $dir): int {
        $x = $a['values'][$sortField] ?? null;
        $y = $b['values'][$sortField] ?? null;

        // Empty values always sink to the bottom, whichever direction we sort.
        if ($x === null && $y === null) return 0;
        if ($x === null) return 1;
        if ($y === null) return -1;

        return (is_numeric($x) && is_numeric($y) ? ($x <=> $y) : strcasecmp((string) $x, (string) $y)) * $dir;
    });

    return $rows;
}

function entry_count(int $collectionId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM entries WHERE collection_id = ?');
    $stmt->execute([$collectionId]);
    return (int) $stmt->fetchColumn();
}

/** The field used as an entry's headline, falling back to the first text field. */
function entry_title(array $fields, array $values): string
{
    foreach ($fields as $field) {
        if (in_array($field['type'], ['text', 'longtext'], true)) {
            $value = $values[$field['key']] ?? '';
            if (is_string($value) && trim($value) !== '') {
                return mb_substr(trim($value), 0, 120);
            }
        }
    }

    return 'Untitled';
}

// --- Pages ------------------------------------------------------------------

function page_create(int $projectId, string $title, string $intro = '', ?int $collectionId = null, bool $isHome = false): int
{
    $title = trim($title) !== '' ? trim($title) : 'Page';

    $position = (int) db()->query(
        'SELECT COALESCE(MAX(position), 0) + 1 FROM pages WHERE project_id = ' . $projectId
    )->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO pages (project_id, title, slug, intro, collection_id, is_home, position)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $projectId, $title, slugify($title, 'page'), trim($intro),
        $collectionId, $isHome ? 1 : 0, $position,
    ]);

    return (int) db()->lastInsertId();
}

function pages_for(int $projectId): array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE project_id = ? ORDER BY is_home DESC, position, id');
    $stmt->execute([$projectId]);
    return $stmt->fetchAll();
}

function page_get(int $id, int $projectId): ?array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE id = ? AND project_id = ?');
    $stmt->execute([$id, $projectId]);
    return $stmt->fetch() ?: null;
}

function page_by_slug(int $projectId, string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE project_id = ? AND slug = ?');
    $stmt->execute([$projectId, $slug]);
    return $stmt->fetch() ?: null;
}

function page_home(int $projectId): ?array
{
    $stmt = db()->prepare('SELECT * FROM pages WHERE project_id = ? ORDER BY is_home DESC, position, id LIMIT 1');
    $stmt->execute([$projectId]);
    return $stmt->fetch() ?: null;
}

function page_update(int $id, int $projectId, string $title, string $intro, string $body, ?int $collectionId): void
{
    $stmt = db()->prepare(
        'UPDATE pages SET title = ?, slug = ?, intro = ?, body = ?, collection_id = ?
         WHERE id = ? AND project_id = ?'
    );
    $stmt->execute([
        trim($title), slugify($title, 'page'), trim($intro), trim($body),
        $collectionId, $id, $projectId,
    ]);
}

function page_delete(int $id, int $projectId): void
{
    db()->prepare('DELETE FROM pages WHERE id = ? AND project_id = ? AND is_home = 0')
        ->execute([$id, $projectId]);
}

/**
 * A new app starts with a home page so the live site is never blank.
 */
function app_scaffold(int $projectId, string $name): void
{
    page_create(
        $projectId,
        'Home',
        'Welcome to ' . $name . '. Use the builder to add your own words here.',
        null,
        true
    );
}
