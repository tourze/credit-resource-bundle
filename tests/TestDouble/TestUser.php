<?php

namespace CreditResourceBundle\Tests\TestDouble;

use Symfony\Component\Security\Core\User\UserInterface;

class TestUser implements UserInterface
{
    private string $id;
    
    public function __construct(string $id)
    {
        $this->id = $id;
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
    
    public function eraseCredentials(): void
    {
        // Not needed for tests
    }
    
    public function getUserIdentifier(): string
    {
        return $this->id;
    }
} 