<?php

declare(strict_types=1);

namespace Modules\Library\Infrastructure\Persistence;

use Modules\Library\Domain\Book;
use Modules\Library\Domain\BookId;
use Modules\Library\Domain\BookRepositoryInterface;
use Modules\Library\Domain\BookTitle;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\DomainException;
use Zana\Ddd\Domain\EntityNotFoundException;

/**
 * Stores Book aggregates in the books table. add() inserts a new one and
 * updates a stored one, refusing a change made to an outdated copy. To store
 * a property: add its column to the migration, then to row() and hydrate().
 */
final readonly class DatabaseBookRepository implements BookRepositoryInterface
{
    private const string TABLE = 'books';

    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(mixed $id): ?Book
    {
        $row = $this->db->table(self::TABLE)->where('id', (string) $id)->first();

        return $row === null ? null : self::hydrate($row);
    }

    public function get(mixed $id): Book
    {
        return $this->find($id) ?? throw new EntityNotFoundException(sprintf('There is no book %s.', (string) $id));
    }

    public function add(AggregateRoot $aggregate): void
    {
        $entity = self::entity($aggregate);
        $version = $entity->getVersion();
        $row = [...self::row($entity), 'version' => $version + 1];

        if ($version === 0) {
            $this->db->table(self::TABLE)->insert(['id' => (string) $entity->id(), ...$row]);
        } elseif ($this->db->table(self::TABLE)->where('id', (string) $entity->id())->where('version', $version)->update($row) !== 1) {
            throw new DomainException(sprintf('The book %s was changed in the meantime; load it again.', (string) $entity->id()));
        }
        $entity->incrementVersion();
    }

    public function remove(AggregateRoot $aggregate): void
    {
        $this->db->table(self::TABLE)->where('id', (string) self::entity($aggregate)->id())->delete();
    }

    public function all(): array
    {
        return array_map(self::hydrate(...), $this->db->table(self::TABLE)->orderBy('title')->get());
    }

    // region: columns
    /**
     * The stored columns, besides id and version.
     *
     * @return array<string, mixed>
     */
    private static function row(Book $entity): array
    {
        return ['title' => $entity->title->value, 'author' => $entity->author];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Book
    {
        $text = static fn (string $key): string => is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';

        return Book::restore(BookId::fromString($text('id')), (int) $text('version'), new BookTitle($text('title')), $text('author'));
    }
    // endregion: columns

    private static function entity(AggregateRoot $aggregate): Book
    {
        return $aggregate instanceof Book
            ? $aggregate
            : throw new \InvalidArgumentException('Expected a ' . Book::class . ', got ' . $aggregate::class . '.');
    }
}
