# Calendar GraphQL

Enable Statamic GraphQL and this addon's query separately from the REST API:

```env
STATAMIC_GRAPHQL_ENABLED=true
STATAMIC_CALENDAR_GRAPHQL_ENABLED=true
```

The addon intentionally exposes cached core calendar fields independently from collection permissions. Native GraphQL endpoint authorization still applies. Send the query in `calendar-occurrences.graphql` to Statamic's native `/graphql` endpoint. It returns a paginated root with `data`, `total`, `per_page`, and `current_page`. Dates use ISO 8601 strings. `status` accepts the `upcoming`, `ongoing`, and `past` enum values. `tags` is an OR list.

Cached extras need an explicit GraphQL schema. Add values while occurrences are built, then rebuild the occurrence cache before querying them. A cache built before the listener exists has no extra values:

```php
use ElSchneider\StatamicCalendar\Events\OccurrenceBuilding;
use Illuminate\Support\Facades\Event;

Event::listen(OccurrenceBuilding::class, function (OccurrenceBuilding $event): void {
    $event->extra['attendance'] = (int) $event->entry->get('attendance');
    $event->extra['venue'] = ['name' => $event->entry->get('venue_name')];
});
```

Register those fields from an application service provider. Each resolver receives the cached occurrence array as its root value:

```php
use App\GraphQL\VenueType;
use ElSchneider\StatamicCalendar\GraphQL\Types\CalendarOccurrenceType;
use Statamic\Facades\GraphQL;

GraphQL::addType(VenueType::class);
GraphQL::addField(CalendarOccurrenceType::NAME, 'attendance', fn () => [
    'type' => GraphQL::nonNull(GraphQL::int()),
    'resolve' => fn (array $occurrence) => $occurrence['attendance'],
]);
GraphQL::addField(CalendarOccurrenceType::NAME, 'venue', fn () => [
    'type' => GraphQL::type(VenueType::NAME),
    'resolve' => fn (array $occurrence) => $occurrence['venue'],
]);
```

```php
namespace App\GraphQL;

use Rebing\GraphQL\Support\Type;
use Statamic\Facades\GraphQL;

class VenueType extends Type
{
    public const NAME = 'EventVenue';

    protected $attributes = ['name' => self::NAME];

    public function fields(): array
    {
        return [
            'name' => ['type' => GraphQL::string()],
        ];
    }
}
```
