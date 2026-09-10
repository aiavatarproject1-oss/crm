<?php

namespace App\Domain\Knowledge\ValueObjects;

enum KnowledgeType: string
{
    case PERSONA = 'PERSONA';
    case FAQ = 'FAQ';
    case PRODUCT = 'PRODUCT';
    case CAMPAIGN = 'CAMPAIGN';
    case POLICY = 'POLICY';
    case BACKGROUND = 'BACKGROUND';
    case PRIVATE_NOTE = 'PRIVATE_NOTE';
}
