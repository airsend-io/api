<?php declare(strict_types=1);


namespace CodeLathe\Core\Policy;


use CodeLathe\Core\Data\DataController;
use CodeLathe\Core\Exception\UnconfiguredPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyException;
use CodeLathe\Core\Exception\UnsupportedPolicyTypeException;
use CodeLathe\Core\Objects\Policy;
use CodeLathe\Core\Policy\Policies\AbstractPolicy;
use Psr\Log\LoggerInterface;

abstract class PolicyProvider
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DataController
     */
    protected $dataController;

    /**
     * Return one dimensional array of supported policy list
     */
    public abstract function getSupportedPolicies() : array;

    /**
     * @param string $key
     * @param $value
     * @return mixed
     */
    public abstract function setPolicyForKey(string $key, string $value): bool;

    /**
     * @param string $key
     * @return Policy
     */
    protected abstract function getPolicyForKey(string $key): ?Policy;

    /**
     * @param string $key
     * @return bool
     */
    public function isSupportedPolicy(string $key): bool
    {
        return in_array($key, $this->getSupportedPolicies());
    }

    /**
     * Return value for supplied policy
     * @param string|AbstractPolicy $policyClass
     * @return mixed
     * @throws UnconfiguredPolicyException
     * @throws UnsupportedPolicyException
     * @throws UnsupportedPolicyTypeException
     */
    public function getPolicyValue(string $policyClass)
    {

        if (!$this->isSupportedPolicy($policyClass)) {
            throw new UnsupportedPolicyException("$policyClass is not supported by " . static::class);
        }

        $key = $policyClass::getKey();

        if (($policyRegister = $this->getPolicyForKey($key)) instanceof Policy) {
            /** @var AbstractPolicy $policy */
            $policy = new $policyClass($policyRegister->getPolicyValue());
            return $policy->getValue();
        } elseif (($default = $policyClass::getDefault()) !== null) {
            return $default;
        }

        throw new UnconfiguredPolicyException("No value for $key found!");
    }

    /**
     * @param string|AbstractPolicy $policyClass
     * @param string $value
     * @throws UnsupportedPolicyException
     */
    public function setPolicyValue(string $policyClass, string $value): void
    {
        if (!$this->isSupportedPolicy($policyClass)) {
            throw new UnsupportedPolicyException("$policyClass is not supported by " . static::class);
        }

        $key = $policyClass::getKey();

        $this->setPolicyForKey($key, $value);
    }
}