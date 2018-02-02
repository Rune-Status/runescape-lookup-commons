<?php

namespace Villermen\RuneScape\ActivityFeed;

class ActivityFeed
{
    /** @var ActivityFeedItem[] */
    protected $items = [];

    /**
     * @param ActivityFeedItem[] $items
     */
    public function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    /**
     * @return ActivityFeedItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Returns all feed items in this feed that occur after the given item.
     *
     * @param ActivityFeedItem $targetItem
     * @return ActivityFeedItem[]
     */
    public function getItemsAfter(ActivityFeedItem $targetItem): array
    {
        // TODO: Work with date, title and description instead of id
        // TODO: Duplicate entries are skipped this way? Yes.
        $newerItems = [];

        foreach($this->getItems() as $item) {
            if ($targetItem->getId() === $item->getId()) {
                break;
            }

            $newerItems[] = $item;
        }

        return $newerItems;
    }

    /**
     * Merges this ActivityFeed with a newer feed.
     * Returns a new feed with all new items from the newerFeed prepended to it.
     *
     * @param ActivityFeed $newerFeed
     * @return ActivityFeed
     */
    public function merge(ActivityFeed $newerFeed): ActivityFeed
    {
        if (count($this->getItems()) > 0) {
            $prepend = $newerFeed->getItemsAfter($this->getItems()[0]);
        } else {
            $prepend = $newerFeed->getItems();
        }

        return new ActivityFeed(array_merge($prepend, $this->getItems()));
    }
}
