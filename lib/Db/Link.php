<?php
declare(strict_types=1);

namespace OCA\DashLink\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getUrl()
 * @method void setUrl(string $url)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string|null getIconPath()
 * @method void setIconPath(?string $iconPath)
 * @method string|null getIconMimeType()
 * @method void setIconMimeType(?string $iconMimeType)
 * @method string getTarget()
 * @method void setTarget(string $target)
 * @method string|null getGroupsJson()
 * @method void setGroupsJson(?string $groupsJson)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method int getEnabled()
 * @method void setEnabled(int $enabled)
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class Link extends Entity implements JsonSerializable {
	protected $title = '';
	protected $url = '';
	protected $description;
	protected $iconPath;
	protected $iconMimeType;
	protected $target = '_blank';
	protected $groupsJson;
	protected $position = 0;
	protected $enabled = 1;
	protected ?string $userId = null;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('title', 'string');
		$this->addType('url', 'string');
		$this->addType('description', 'string');
		$this->addType('iconPath', 'string');
		$this->addType('iconMimeType', 'string');
		$this->addType('target', 'string');
		$this->addType('groupsJson', 'string');
		$this->addType('position', 'integer');
		$this->addType('enabled', 'integer');
		$this->addType('userId', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * Get groups as array
	 */
	public function getGroups(): array {
		if ($this->groupsJson === null || $this->groupsJson === '') {
			return [];
		}
		$groups = json_decode($this->groupsJson, true);
		return is_array($groups) ? $groups : [];
	}

	/**
	 * Set groups from array
	 */
	public function setGroups(array $groups): void {
		$this->setGroupsJson(json_encode($groups));
	}

	/**
	 * Check if this is a user-private link
	 */
	public function isUserLink(): bool {
		return $this->userId !== null;
	}

	/**
	 * Check if this is an admin/global link
	 */
	public function isAdminLink(): bool {
		return $this->userId === null;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'url' => $this->getUrl(),
			'description' => $this->getDescription(),
			'iconPath' => $this->getIconPath(),
			'iconMimeType' => $this->getIconMimeType(),
			'target' => $this->getTarget(),
			'groups' => $this->getGroups(),
			'position' => $this->getPosition(),
			'enabled' => $this->getEnabled(),
			'userId' => $this->getUserId(),
			'createdAt' => $this->getCreatedAt()?->format(\DateTime::ATOM),
			'updatedAt' => $this->getUpdatedAt()?->format(\DateTime::ATOM),
		];
	}
}
