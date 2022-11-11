<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\Controllers;

use BanModel;
use League\Uri\Http;
use VanillaTests\SetupTraitsTrait;
use VanillaTests\SiteTestTrait;
use VanillaTests\VanillaTestCase;

/**
 * Tests for the `EntryController` class.
 *
 * These tests aren't exhaustive. If more tests are added then we may need to tweak this class to use the `SiteTestTrait`.
 */
class EntryControllerTest extends VanillaTestCase
{
    use SiteTestTrait, SetupTraitsTrait;

    /**
     * @var \EntryController
     */
    private $controller;

    private $userData;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTraits();

        $this->controller = $this->container()->get(\EntryController::class);
        $this->controller->getImports();
        $this->controller->Request = $this->container()->get(\Gdn_Request::class);
        $this->controller->initialize();
        $this->userData = $this->insertDummyUser();
    }

    /**
     * Test if form fields are generated correctly according to profile fields.
     */
    public function testGenerateFormCustomProfileFields(): void
    {
        $this->runWithConfig(
            ["Garden.Registration.Method" => "Basic", "Feature.CustomProfileFields.Enabled" => true],
            function () {
                //first create some profile fields with different formType/dataType
                $this->createProfileField(["apiName" => "profile-field-test-textInput"]);
                $this->createProfileField([
                    "apiName" => "profile-field-test-dropdown",
                    "label" => "profile field test dropdown",
                    "formType" => "dropdown",
                    "dataType" => "number",
                    "dropdownOptions" => [
                        "0" => 0,
                        "1" => 1,
                    ],
                ]);
                $this->createProfileField([
                    "apiName" => "profile-field-test-checkbox",
                    "label" => "profile field test checkbox",
                    "dataType" => "boolean",
                    "formType" => "checkbox",
                ]);

                $expected = '<li class="form-group"><label for="Form_profile-field-test-textInput">Profile Field Test</label>
<input type="text" id="Form_profile-field-test-textInput" name="profile-field-test-textInput" value="" class="InputBox" /></li><li class="form-group"><label for="Form_profile-field-test-dropdown">Profile Field Test Dropdown</label>
<select id="Form_profile-field-test-dropdown" name="profile-field-test-dropdown" class="" data-value="">
<option value=""></option>
<option value="0">0</option>
<option value="1">1</option>
</select></li><li><label for="Form_profile-field-test-checkbox" class="CheckBoxLabel"><input type="hidden" name="Checkboxes[]" value="profile-field-test-checkbox" /><input type="checkbox" id="Form_profile-field-test-checkbox" name="profile-field-test-checkbox" value="1" class="" /> Profile Field Test Checkbox</label></li>';

                $this->expectOutputString($expected);
                $this->controller->generateFormCustomProfileFields();
            }
        );
    }

    /**
     * Test a basic registration flow with custom profile fields, with a required field.
     */
    public function testRegisterBasicWithCustomProfileFields(): void
    {
        $this->runWithConfig(
            ["Garden.Registration.Method" => "Basic", "Feature.CustomProfileFields.Enabled" => true],
            function () {
                //first create some profile fields
                $result = $this->createProfileField([
                    "apiName" => "profile-field-test-textBox",
                    "label" => "profile field test textBox",
                    "registrationOptions" => "required",
                ]);

                $this->assertEquals(201, $result->getStatusCode());

                //We check if our custom field exists on the registration page.
                $registerPage = $this->bessy()->getHtml("/entry/register");
                $registerPage->assertCssSelectorExists("#Form_profile-field-test-textBox");

                $formFields = [
                    "Email" => "new@user.com",
                    "Name" => "NewUserName",
                    "profile-field-test-textBox" => "test",
                    "Password" => "jXM>e!gL4#38cP3Z",
                    "PasswordMatch" => "jXM>e!gL4#38cP3Z",
                    "TermsOfService" => "1",
                    "Save" => "Save",
                ];

                $registrationResults = $this->bessy()->post("/entry/register", $formFields);

                //success
                $this->assertIsObject($registrationResults);
                $this->assertNotEmpty($registrationResults->Form->_FormValues["profile-field-test-textBox"]);
                $this->assertNotEmpty($registrationResults->Data["UserID"]);

                // Trying to register providing an empty required field, this will fail.
                $formFields["profile-field-test-textBox"] = "";
                $this->expectExceptionMessage("profile field test textBox is required");
                $validationFailResults = $this->bessy()->post("/entry/register", $formFields);
            }
        );
    }

    /**
     * Create profile fields.
     *
     * @param array $options
     */
    public function createProfileField(array $options = [])
    {
        $initialData = [
            "apiName" => "profile-field-test",
            "label" => "profile field test",
            "description" => "this is a test",
            "dataType" => "text",
            "formType" => "text",
            "visibility" => "public",
            "mutability" => "all",
            "displayOptions" => ["profiles" => true, "userCards" => true, "posts" => true],
            "registrationOptions" => "optional",
        ];

        return $this->api()->post("/profile-fields", array_merge($initialData, $options));
    }

    /**
     * Target URLs should be checked for safety and UX.
     *
     * @param string|false $url
     * @param string $expected
     * @dataProvider provideTargets
     */
    public function testTarget($url, string $expected): void
    {
        $expected = url($expected, true);
        $actual = $this->controller->target($url);
        $this->assertSame($expected, $actual);
    }

    /**
     * Test Target as an empty string.
     */
    public function testEmptyTarget(): void
    {
        $expected = url("/", true);
        $this->controller->Request->setQuery(["target" => ""]);
        $actual = $this->controller->target(false);
        $this->assertSame($expected, $actual);
    }

    /**
     * The querystring and form should control the target.
     */
    public function testTargetFallback(): void
    {
        $target = url("/foo", true);
        $this->controller->Request->setQuery(["target" => $target]);

        $this->assertSame($target, $this->controller->target());

        $target2 = url("/bar", true);
        $this->controller->Form->setFormValue("Target", $target2);
        $this->assertSame($target2, $this->controller->target());
    }

    /**
     * Provide some sign out target tests.
     *
     * @return array
     */
    public function provideTargets(): array
    {
        $r = [
            ["/foo", "/foo"],
            ["entry/signin", "/"],
            ["entry/signout?foo=bar", "/"],
            ["/entry/autosignedout", "/"],
            ["/entry/autosignedout234", "/entry/autosignedout234"],
            ["https://danger.test/hack", "/"],
            [false, "/"],
        ];

        return array_column($r, null, 0);
    }

    /**
     * Test a basic registration flow.
     */
    public function testRegisterBasic(): void
    {
        $this->runWithConfig(["Garden.Registration.Method" => "Basic"], function () {
            $user = self::sprintfCounter([
                "Name" => "test%s",
                "Email" => "test%s@example.com",
                "Password" => __FUNCTION__,
                "PasswordMatch" => __FUNCTION__,
                "TermsOfService" => "1",
            ]);

            $r = $this->bessy()->post("/entry/register", $user);
            $welcome = $this->assertEmailSentTo($user["Email"]);

            // The user has registered. Let's simulate clicking on the confirmation email.
            $emailUrl = Http::createFromString($welcome->template->getButtonUrl());
            $this->assertStringContainsString("/entry/emailconfirm", $emailUrl->getPath());

            parse_str($emailUrl->getQuery(), $query);
            $this->assertArraySubsetRecursive(
                [
                    "vn_medium" => "email",
                    "vn_campaign" => "welcome",
                    "vn_source" => "register",
                ],
                $query
            );

            $r2 = $this->bessy()->get($welcome->template->getButtonUrl(), [], []);
            $this->assertTrue($r2->data("EmailConfirmed"));
            $this->assertSame((int) $r->data("UserID"), \Gdn::session()->UserID);
        });
    }

    /**
     * If account has been banned by a ban rule.
     */
    public function testBannedAutomaticSignin(): void
    {
        $postBody = ["Email" => $this->userData["Email"], "Password" => $this->userData["Email"], "RememberMe" => 1];

        $this->userData = $this->userModel->getID($this->userData["UserID"], DATASET_TYPE_ARRAY);
        $banned = val("Banned", $this->userData, 0);
        $userData = [
            "UserID" => $this->userData["UserID"],
            "Banned" => BanModel::setBanned($banned, true, BanModel::BAN_AUTOMATIC),
        ];
        $this->userModel->save($userData);

        $this->expectExceptionMessage(t("This account has been banned."));
        $r = $this->bessy()->post("/entry/signin", $postBody);
    }

    /**
     * If account has been banned manually.
     */
    public function testBannedManualSignin(): void
    {
        $postBody = ["Email" => $this->userData["Email"], "Password" => $this->userData["Email"], "RememberMe" => 1];

        $this->userData = $this->userModel->getID($this->userData["UserID"], DATASET_TYPE_ARRAY);
        $banned = val("Banned", $this->userData, 0);
        $userData = [
            "UserID" => $this->userData["UserID"],
            "Banned" => BanModel::setBanned($banned, true, BanModel::BAN_MANUAL),
        ];
        $this->userModel->save($userData);

        $this->expectExceptionMessage(t("This account has been banned."));
        $r = $this->bessy()->post("/entry/signin", $postBody);
    }

    /**
     * If account has been banned by the "Warnings and notes" plugin or similar.
     */
    public function testBannedWarningSignin(): void
    {
        $postBody = ["Email" => $this->userData["Email"], "Password" => $this->userData["Email"], "RememberMe" => 1];

        $this->userData = $this->userModel->getID($this->userData["UserID"], DATASET_TYPE_ARRAY);
        $banned = val("Banned", $this->userData, 0);
        $userData = [
            "UserID" => $this->userData["UserID"],
            "Banned" => BanModel::setBanned($banned, true, BanModel::BAN_WARNING),
        ];
        $this->userModel->save($userData);

        $this->expectExceptionMessage(t("This account has been temporarily banned."));
        $r = $this->bessy()->post("/entry/signin", $postBody);
    }

    /**
     * Test checkAccessToken().
     *
     * @param string $path
     * @param bool $valid
     * @dataProvider providePathData
     */
    public function testTokenAuthentication(string $path, bool $valid): void
    {
        /** @var \Gdn_Session $session */
        $session = self::container()->get(\Gdn_Session::class);
        $session->start([1]);
        $userID = $this->createUserFixture(VanillaTestCase::ROLE_MEMBER);
        /** @var \AccessTokenModel $tokenModel */
        $tokenModel = $this->container()->get(\AccessTokenModel::class);
        $tokenModel->issue($userID);
        $accessToken = $tokenModel->getWhere(["UserID" => $userID])->firstRow(DATASET_TYPE_ARRAY);
        $signedToken = $tokenModel->signTokenRow($accessToken);
        $_SERVER["HTTP_AUTHORIZATION"] = "Bearer " . $signedToken;
        $session->end();
        \Gdn::request()->setPath($path);
        /** @var \Gdn_Auth $auth */
        $auth = $this->container()->get(\Gdn_Auth::class);
        $auth->startAuthenticator();
        if ($valid) {
            $this->assertEquals($userID, \Gdn::session()->UserID);
        } else {
            $this->assertEquals(0, \Gdn::session()->UserID);
        }
    }

    /**
     * Provide path data.
     *
     * @return array
     */
    public function providePathData(): array
    {
        return [
            "valid-path" => ["api/v2", true],
            "valid-path-subc" => ["subc/api/v2", true],
            "invalid-path" => ["/invalid", false],
            "invalid-path-subc" => ["/subc1/subc2/api/v2", false],
        ];
    }
}
