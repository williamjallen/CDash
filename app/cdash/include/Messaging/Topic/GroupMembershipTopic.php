<?php

namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class GroupMembershipTopic extends Topic
{
    /**
     * @var string
     */
    private $group;

    /**
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $parentTopic && $this->group === $build->GetBuildType();
        return $subscribe;
    }

    /**
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return int
     */
    public function getTopicCount()
    {
        if ($this->topic) {
            return $this->topic->getTopicCount();
        }
        return 0;
    }

    /**
     * @return bool
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // TODO: q: do we need to do this again here?
        // a: Only if subscribesToBuild has not yet been called, but if it has been called
        //    how do we determine that the build passed in here is the same build that was
        //    verified in subscribesToBuild?
        return $this->group === $build->GetBuildType();
    }
}
