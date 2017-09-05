<?php

namespace Villermen\RuneScape\Highscore;

use DateTime;
use Iterator;
use Villermen\RuneScape\Constants;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\RuneScapeException;

/**
 * Represents a player's highscore at a specific moment in time.
 */
class Highscore implements Iterator
{
    /** @var Player|null*/
    private $player;

    /** @var string */
    private $rawData;

    /** @var HighscoreSkill[]  */
    private $skills = [];

    /** @var HighscoreActivity[] */
    private $activities = [];

    /** @var DateTime */
    private $time;

    /** @var int */
    private $iteratorKey;

    /**
     * Creates a Highscore object from a raw high score data response.
     *
     * @param Player|null $player
     * @param string $rawData Data as returned from Jagex's lookup API.
     * @param DateTime|null $time
     * @throws RuneScapeException
     */
    public function __construct(Player $player, string $rawData, DateTime $time = null)
    {
        $this->player = $player;
        $this->rawData = $rawData;

        if ($time) {
            $this->time = $time;
        } else {
            $this->time = new DateTime();
        }

        $entries = explode("\n", trim($rawData));

        $skillId = 0;
        $activityId = 0;

        $skills = Constants::getSkills();
        $activities = Constants::getActivities();

        foreach($entries as $entry) {
            $entryArray = explode(",", $entry);

            if (count($entryArray) == 3) {
                // Skill
                list($rank, $level, $xp) = $entryArray;

                if (isset($skills[$skillId])) {
                    $this->skills[] = new HighscoreSkill($skills[$skillId], $rank, $level, $xp);
                }

                $skillId++;
            } elseif (count($entryArray) == 2) {
                // Activity
                list($rank, $score) = $entryArray;

                if (isset($activities[$activityId])) {
                    $this->activities[] = new HighscoreActivity($activities[$activityId], $rank, $score);
                }

                $activityId++;
            } else {
                throw new RuneScapeException("Invalid highscore data supplied.");
            }
        }

        if (!$skillId) {
            throw new RuneScapeException("No highscore obtained from data.");
        }
    }

    /**
     * Returns the combat level of this highscore.
     *
     * @param bool $includeSummoning Whether to include the summoning skill while calculating the combat level.
     * @param bool $uncapped
     * @return int
     */
    public function getCombatLevel($includeSummoning = true, $uncapped = false): int
    {
        $attackLevel = $this->getSkill(Constants::SKILL_ATTACK)->getLevel($uncapped);
        $defenceLevel = $this->getSkill(Constants::SKILL_DEFENCE)->getLevel($uncapped);
        $strengthLevel = $this->getSkill(Constants::SKILL_STRENGTH)->getLevel($uncapped);
        $constitutionLevel = $this->getSkill(Constants::SKILL_CONSTITUTION)->getLevel($uncapped);
        $rangedLevel = $this->getSkill(Constants::SKILL_RANGED)->getLevel($uncapped);
        $prayerLevel = $this->getSkill(Constants::SKILL_PRAYER)->getLevel($uncapped);
        $magicLevel = $this->getSkill(Constants::SKILL_MAGIC)->getLevel($uncapped);

        $summoningSkill = $this->getSkill(Constants::SKILL_SUMMONING);

        if ($includeSummoning && $summoningSkill) {
            $summoningLevel = $summoningSkill->getLevel($uncapped);
        } else {
            $summoningLevel = 1;
        }


        return (int)((
            max($attackLevel + $strengthLevel, $magicLevel * 2, $rangedLevel * 2) * 1.3 +
            $defenceLevel + $constitutionLevel +
            floor($prayerLevel / 2) + floor($summoningLevel / 2)
        ) / 4);
    }

    /**
     * @return string
     */
    public function getRawData(): string
    {
        return $this->rawData;
    }

    /**
     * @return DateTime
     */
    public function getTime(): DateTime
    {
        return $this->time;
    }

    /**
     * @return Player|null
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @return HighscoreEntry[]
     */
    public function getEntries(): array
    {
        return array_merge($this->getSkills(), $this->getActivities());
    }

    /**
     * @return HighscoreSkill[]
     */
    public function getSkills(): array
    {
        return $this->skills;
    }

    /**
     * @return HighscoreActivity[]
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * @param $id
     * @return HighscoreSkill|null
     */
    public function getSkill($id): HighscoreSkill
    {
        foreach($this->getSkills() as $skill) {
            if ($skill->getSkill()->getId() === $id)  {
                return $skill;
            }
        }

        return null;
    }

    /**
     * @param $id
     * @return HighscoreActivity|null
     */
    public function getActivity($id): HighscoreActivity
    {
        foreach($this->getActivities() as $activity) {
            if ($activity->getActivity()->getId() === $id)  {
                return $activity;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     * @return HighscoreEntry
     */
    public function current(): HighscoreEntry
    {
        return $this->getEntries()[$this->iteratorKey];
    }

    /** @inheritdoc */
    public function next(): void
    {
        $this->iteratorKey++;
    }

    /** @inheritdoc */
    public function key(): int
    {
        return $this->iteratorKey;
    }

    /** @inheritdoc */
    public function valid(): bool
    {
        return isset($this->getEntries()[$this->iteratorKey]);
    }

    /** @inheritdoc */
    public function rewind(): void
    {
        $this->iteratorKey = 0;
    }
}
