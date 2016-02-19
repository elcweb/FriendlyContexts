<?php

namespace Elcweb\FriendlyContexts\Context;

use Behat\Behat\Context\Context as ContextInterface;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Symfony2Extension\Driver\KernelDriver;
use Knp\FriendlyContexts\Context\MinkContext as KnpMinkContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Class MinkContext
 * @package Elcweb\FriendlyContexts\Context
 */
class MinkContext extends KnpMinkContext implements ContextInterface
{
    // Redirection interception
    /**
     * @ Given /^(.*) without redirection$/
     * @Given I disable the redirection
     */
    public function iDisableTheRedirections()
    {
        $this->canIntercept();
        $this->getSession()->getDriver()->getClient()->followRedirects(false);
    }

    /**
     * @throws UnsupportedDriverActionException
     */
    public function canIntercept()
    {
        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof KernelDriver) {
            throw new UnsupportedDriverActionException(
                'You need to tag the scenario with ' .
//                '"@mink:goutte" or "@mink:symfony2". '.
                "@mink:symfony2" .
                'Intercepting the redirections is not ' .
                'supported by %s', $driver
            );
        }
    }

    /**
     * @When /^I follow the redirection$/
     * @Then /^I should be redirected$/
     */
    public function iFollowTheRedirection()
    {
        $this->canIntercept();
        $client = $this->getSession()->getDriver()->getClient();
        $client->followRedirects(true);
        $client->followRedirect();
    }

    // E-Mails

    /**
     * @Given /^I should get an email on "(?P<email>[^"]+)" with:$/
     */
    public function iShouldGetAnEmail($email, PyStringNode $text)
    {
        $error = sprintf('No message sent to "%s"', $email);
        $profile = $this->getSymfonyProfile();
        $collector = $profile->getCollector('swiftmailer');

        foreach ($collector->getMessages() as $message) {
            // Checking the recipient email and the X-Swift-To
            // header to handle the RedirectingPlugin.
            // If the recipient is not the expected one, check
            // the next mail.
            $correctRecipient = array_key_exists(
                $email, $message->getTo()
            );
            $headers = $message->getHeaders();
            $correctXToHeader = false;
            if ($headers->has('X-Swift-To')) {
                $correctXToHeader = array_key_exists($email,
                    $headers->get('X-Swift-To')->getFieldBodyModel()
                );
            }

            if (!$correctRecipient && !$correctXToHeader) {
                continue;
            }

            if ($this->getAsserter()->assert(
                strpos($message->getBody(), $text->getRaw()), 'Text not found in email'
            )
            ) {
                return true;
            } else {
                $error = sprintf(
                    'An email has been found for "%s" but without ' .
                    'the text "%s".', $email, $text->getRaw()
                );
            }
        }

        throw new ExpectationException($error, $this->getSession());
    }

    /**
     * @return Profile
     * @throws UnsupportedDriverActionException
     */
    public function getSymfonyProfile()
    {
        /** @var KernelDriver $driver */
        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof KernelDriver) {
            throw new UnsupportedDriverActionException(
                'You need to tag the scenario with ' .
                '"@mink:symfony2". Using the profiler is not ' .
                'supported by %s', $driver
            );
        }

        /** @var Profile $profile */
        $profile = $driver->getClient()->getProfile();
        if (false === $profile) {
            throw new \RuntimeException(
                'The profiler is disabled. Activate it by setting ' .
                'framework.profiler.only_exceptions to false in ' .
                'your config'
            );
        }

        return $profile;
    }
}
