<?php
declare(strict_types=1);

namespace OCA\DashLink\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return 'dashlink';
	}

	public function getName(): string {
		return $this->l10n->t('DashLink');
	}

	public function getPriority(): int {
		return 50;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('dashlink', 'app.svg');
	}
}
