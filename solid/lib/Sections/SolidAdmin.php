<?php
namespace OCA\Solid\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class SolidAdmin implements IIconSection {
    private IL10N $l;
    private IURLGenerator $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getIcon(): string {
        return $this->urlGenerator->imagePath('solid', 'app.svg');
    }

    public function getID(): string {
        return 'solid';
    }

    public function getName(): string {
        return $this->l->t('Solid');
    }

    public function getPriority(): int {
        return 98;
    }
}