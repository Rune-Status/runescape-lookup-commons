<?php

namespace Villermen\RuneScape;

use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\Exception\DataConversionException;
use Villermen\RuneScape\HighScore\ActivityHighScore;
use Villermen\RuneScape\HighScore\HighScoreActivity;
use Villermen\RuneScape\HighScore\HighScoreSkill;
use Villermen\RuneScape\HighScore\SkillHighScore;

/**
 * Converts data obtained from Jagex APIs into objects. Methods return arrays of converted objects by their key.
 */
class PlayerDataConverter
{
    const KEY_REAL_NAME = "realName";
    const KEY_SKILL_HIGH_SCORE = "skillHighScore";
    const KEY_OLD_SCHOOL_SKILL_HIGH_SCORE = "oldSchoolSkillHighScore";
    const KEY_ACTIVITY_HIGH_SCORE = "activityHighScore";
    const KEY_OLD_SCHOOL_ACTIVITY_HIGH_SCORE = "oldSchoolActivityHighScore";
    const KEY_ACTIVITY_FEED = "activityFeed";

    /**
     * Yields {@see KEY_SKILL_HIGH_SCORE} and {@see KEY_ACTIVITY_HIGH_SCORE}.
     *
     * @return mixed[]
     * @throws DataConversionException
     */
    public function convertIndexLite(string $data): array
    {
        return $this->convertIndexLiteInternal($data, false);
    }

    /**
     * Yields {@see KEY_OLD_SCHOOL_SKILL_HIGH_SCORE} and {@see KEY_OLDSCHOOL_ACTIVITY_HIGH_SCORE}.
     *
     * @return mixed[]
     * @throws DataConversionException
     */
    public function convertOldSchoolIndexLite(string $data): array
    {
        return $this->convertIndexLiteInternal($data, true);
    }

    /**
     * @return mixed[]
     * @throws DataConversionException
     */
    private function convertIndexLiteInternal(string $data, bool $oldSchool): array
    {
        // Parse data into HighScore object
        $entries = explode("\n", trim($data));

        $skillId = 0;
        $activityId = 0;
        $skills = [];
        $activities = [];

        foreach($entries as $entry) {
            $entryArray = explode(",", $entry);

            if (count($entryArray) === 3) {
                // Skill
                try {
                    $skill = Skill::getSkill($skillId);
                    list($rank, $level, $xp) = $entryArray;
                    $skills[] = new HighScoreSkill($skill, $rank, $level, $xp);
                } catch (\InvalidArgumentException $exception) {
                    // Unknown (new?) skill
                }

                $skillId++;
            } elseif (count($entryArray) === 2) {
                // Activity
                try {
                    $activity = Activity::getActivity($oldSchool ? $activityId + 1000 : $activityId);
                    list($rank, $score) = $entryArray;

                    $activities[] = new HighScoreActivity($activity, $rank, $score);
                } catch (\InvalidArgumentException $exception) {
                    // Unknown (new?) activity
                }

                $activityId++;
            } else {
                throw new DataConversionException("Invalid high score data supplied.");
            }
        }

        if (!count($skills) && !count($activities)) {
            throw new DataConversionException("No high score obtained from data.");
        }

        $skillHighScoreKey = $oldSchool ? self::KEY_OLD_SCHOOL_SKILL_HIGH_SCORE : self::KEY_SKILL_HIGH_SCORE;
        $activityHighScoreKey = $oldSchool ? self::KEY_OLD_SCHOOL_ACTIVITY_HIGH_SCORE : self::KEY_ACTIVITY_HIGH_SCORE;

        return [
            $skillHighScoreKey => new SkillHighScore($skills),
            $activityHighScoreKey => new ActivityHighScore($activities)
        ];
    }

    /**
     * Yields {@see KEY_REAL_NAME}, {@see KEY_SKILL_HIGH_SCORE} and {@see KEY_ACTIVITY_FEED}.
     *
     * @return mixed[]
     * @throws DataConversionException
     */
    public function convertRuneMetrics(string $data): array
    {
        $data = @json_decode($data);

        if (!$data) {
            throw new DataConversionException("Could not decode RuneMetrics API response.");
        }

        if (isset($data->error)) {
            throw new DataConversionException("RuneMetrics API returned an error. User might not exist.");
        }

        // HighScore
        $skills = [];
        foreach($data->skillvalues as $skillvalue) {
            $skillId = $skillvalue->id + 1;

            try {
                $skill = Skill::getSkill($skillId);
                $xp = (int)($skillvalue->xp / 10);

                $skills[] = new HighScoreSkill($skill, $skillvalue->rank ?? 0, $skillvalue->level, $xp);
            } catch (\InvalidArgumentException $exception) {
                // Unknown (new?) skill
            }
        }

        // Add total
        $skills[] = new HighScoreSkill(
            Skill::getSkill(Skill::SKILL_TOTAL),
            $data->rank ? str_replace(",", "", $data->rank) : 0,
            $data->totalskill, $data->totalxp
        );

        // ActivityFeed
        $activities = [];
        foreach($data->activities as $activity) {
            $time = new \DateTime($activity->date);
            $time->setTimezone(new \DateTimeZone('UTC'));

            $activities[] = new ActivityFeedItem(
                $time,
                trim($activity->text),
                trim($activity->details)
            );
        }

        return [
            self::KEY_SKILL_HIGH_SCORE => new SkillHighScore($skills),
            self::KEY_ACTIVITY_FEED => new ActivityFeed($activities),
            self::KEY_REAL_NAME => $data->name
        ];
    }

    /**
     * Yields {@see KEY_REAL_NAME} and {@see KEY_ACTIVITY_FEED}.
     *
     * @return mixed[]
     * @throws DataConversionException
     */
    public function convertAdventurersLog(string $data): array
    {
        // Parse data into ActivityFeed object
        $feedItems = [];

        try {
            $feed = new \SimpleXmlElement($data);
        } catch (\Exception $exception) {
            throw new DataConversionException("Could not parse the activity feed as XML.");
        }

        $itemElements = @$feed->xpath("/rss/channel/item");

        if ($itemElements === false) {
            throw new DataConversionException("Could not obtain any feed items from feed.");
        }

        foreach ($itemElements as $itemElement) {
            $time = new \DateTime($itemElement->pubDate);
            $time->setTimezone(new \DateTimeZone('UTC'));
            $title = trim((string)$itemElement->title);
            $description = trim((string)$itemElement->description);

            if (!$title || !$description) {
                throw new DataConversionException(sprintf(
                    "Could not parse one of the activity feed items. (time: %s, title: %s, description: %s)",
                    $time->format("j-n-Y"), $title, $description
                ));
            }

            $feedItems[] = new ActivityFeedItem($time, $title, $description);
        }

        // Parse real name
        $titleElements = @$feed->xpath("/rss/channel/title");

        if ($titleElements === false || !count($titleElements)) {
            throw new DataConversionException("Could not obtain player name element from feed.");
        }

        $title = (string)$titleElements[0];
        $name = trim(substr($title, strrpos($title, ":") + 1));

        return [
            self::KEY_ACTIVITY_FEED => new ActivityFeed($feedItems),
            self::KEY_REAL_NAME => $name
        ];
    }
}
