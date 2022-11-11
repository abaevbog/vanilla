<?php
/**
 * @author Andrew Keller <akeller@higherlogic.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\APIv2;

use Garden\Web\Exception\ClientException;
use Vanilla\Dashboard\Models\ProfileFieldModel;
use VanillaTests\ExpectExceptionTrait;
use VanillaTests\UsersAndRolesApiTestTrait;

class ProfileFieldsApiControllerTest extends AbstractResourceTest
{
    use UsersAndRolesApiTestTrait, ExpectExceptionTrait;

    protected $baseUrl = "/profile-fields";

    protected $pk = "apiName";

    protected $patchFields = ["apiName", "label", "description", "enabled"];

    protected $editFields = [];

    protected $testPagingOnIndex = false;

    protected $record = [
        "apiName" => "profile-field-test",
        "label" => "profile field test",
        "description" => "this is a test",
        "dataType" => "text",
        "formType" => "text",
        "visibility" => "public",
        "mutability" => "all",
        "displayOptions" => ["userCards" => true, "posts" => true],
        "registrationOptions" => ProfileFieldModel::REGISTRATION_HIDDEN,
    ];

    public function tearDown(): void
    {
        parent::tearDown();
        \Gdn::sql()->truncate("profileField");
    }

    /**
     * @inheritDoc
     */
    public function testPost($record = null, array $extra = []): array
    {
        $record = $record === null ? $this->record() : $record;
        $post = $record + $extra;
        $result = $this->api()->post($this->baseUrl, $post);

        $this->assertEquals(201, $result->getStatusCode());

        $body = $result->getBody();
        $this->assertTrue(is_string($body[$this->pk]));
        $this->assertNotEmpty($body[$this->pk]);
        $this->assertRowsEqual($record, $body);

        // Test permission error (403)
        $this->runWithUser(function () use ($post) {
            $this->runWithExpectedExceptionCode(403, function () use ($post) {
                $this->api()->post($this->baseUrl, $post);
            });
        }, \UserModel::GUEST_USER_ID);

        return $body;
    }

    /**
     * Test validation on the post endpoint using tests provided by providePostData
     *
     * @param array $postData
     * @param bool $expectException
     * @return void`
     * @dataProvider providePostData
     */
    public function testPostValidation(array $postData, bool $expectException = false)
    {
        if ($expectException) {
            $this->expectException(ClientException::class);
            $this->expectExceptionCode(400);
        }

        $result = $this->api()->post($this->baseUrl, $postData);

        $this->assertEquals(201, $result->getStatusCode());
        $this->assertRowsEqual($postData, $result->getBody());
    }

    /**
     * @inheritDoc
     */
    public function testGet()
    {
        $this->markTestSkipped("This resource doesn't have a GET /profile-fields/{id} endpoint");
    }

    /**
     * @inheritDoc
     */
    public function testGetEdit($record = null)
    {
        $this->markTestSkipped("This resource doesn't have a GET /profile-fields/{id}/edit endpoint");
    }

    /**
     * Test validation on the patch endpoint using tests provided by providePatchData
     *
     * @param array $initialData Combined with $this->record to create initial record
     * @param array $patchData The payload combined with $this->record for the patch endpoint
     * @param bool $shouldExpectException Does this throw validation exception
     * @return void
     *
     * @dataProvider providePatchData
     */
    public function testPatchValidation(array $initialData, array $patchData, bool $shouldExpectException = false)
    {
        $record = $initialData + $this->record();
        $this->api()->post($this->baseUrl, $record);

        if ($shouldExpectException) {
            $this->expectException(ClientException::class);
            $this->expectExceptionCode(400);
        }

        $result = $this->api()->patch("{$this->baseUrl}/{$record[$this->pk]}", $patchData);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertRowsEqual($patchData, $result->getBody());
    }

    /**
     * Performs a basic patch test and tests for some expected exceptions.
     *
     * @return void
     */
    public function testPatch()
    {
        $this->api()->post($this->baseUrl, ["apiName" => "patch_test"] + $this->record());

        $patch = [
            "label" => "patch_test_updated",
            "description" => "updated",
            "formType" => "text",
            "visibility" => "private",
            "mutability" => "restricted",
            "displayOptions" => ["userCards" => false, "posts" => false],
            "registrationOptions" => ProfileFieldModel::REGISTRATION_HIDDEN,
        ];

        // Basic patch test
        $result = $this->api()->patch("{$this->baseUrl}/patch_test", $patch);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertRowsEqual($patch, $result->getBody());

        // Test permission error (403)
        $this->runWithUser(function () use ($patch) {
            $this->runWithExpectedExceptionCode(403, function () use ($patch) {
                $this->api()->patch("{$this->baseUrl}/patch_test", $patch);
            });
        }, \UserModel::GUEST_USER_ID);

        // Test not found error (404)
        $this->runWithExpectedExceptionCode(404, function () use ($patch) {
            $this->api()->patch("{$this->baseUrl}/doesnt_exist", $patch);
        });
    }

    /**
     * Provider for testing various properties are validated and updated
     *
     * @return array
     */
    public function providePatchData(): array
    {
        return [
            [["dataType" => "text", "formType" => "text"], ["formType" => "text"], false],
            [["dataType" => "text", "formType" => "text"], ["formType" => "text-multiline"], false],
            [["dataType" => "text", "formType" => "text"], ["formType" => "dropdown"], true],
            [["dataType" => "text", "formType" => "text"], ["formType" => "tokens"], true],
            [["dataType" => "text", "formType" => "text"], ["formType" => "checkbox"], true],
            [["dataType" => "text", "formType" => "text"], ["formType" => "date"], true],
            [["dataType" => "text", "formType" => "text"], ["formType" => "number"], true],
            [["dateType" => "text", "formType" => "text"], ["dropdownOptions" => ["x", "y", "z"]], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "checkbox"], false],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "text"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "text-multiline"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "dropdown"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "tokens"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "date"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["formType" => "number"], true],
            [["dataType" => "boolean", "formType" => "checkbox"], ["dropdownOptions" => [true, false]], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "date"], false],
            [["dataType" => "date", "formType" => "date"], ["formType" => "text"], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "text-multiline"], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "dropdown"], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "tokens"], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "checkbox"], true],
            [["dataType" => "date", "formType" => "date"], ["formType" => "number"], true],
            [["dataType" => "date", "formType" => "date"], ["dropdownOptions" => ["2222-02-22", "3333-03-03"]], true],
            [["dataType" => "number", "formType" => "number"], ["formType" => "dropdown"], true],
            [
                ["dataType" => "number", "formType" => "number"],
                ["formType" => "dropdown", "dropdownOptions" => [1]],
                false,
            ],
            [["dataType" => "number", "formType" => "number"], ["formType" => "number"], false],
            [["dataType" => "number", "formType" => "number"], ["formType" => "text"], true],
            [["dataType" => "number", "formType" => "number"], ["formType" => "text-multiline"], true],
            [["dataType" => "number", "formType" => "number"], ["formType" => "tokens"], true],
            [["dataType" => "number", "formType" => "number"], ["formType" => "checkbox"], true],
            [["dataType" => "number", "formType" => "number"], ["formType" => "date"], true],
            [["dataType" => "number", "formType" => "number"], ["dropdownOptions" => [1, 2, 3]], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "tokens"], false],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "text"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "text-multiline"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "dropdown"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "checkbox"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "date"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["formType" => "number"], true],
            [["dataType" => "number[]", "formType" => "tokens"], ["dropdownOptions" => [1, 2, 3]], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "tokens"], false],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "text"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "text-multiline"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "dropdown"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "checkbox"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "date"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["formType" => "number"], true],
            [["dataType" => "string[]", "formType" => "tokens"], ["dropdownOptions" => ["x", "y", "z"]], true],
            [
                ["visibility" => "public", "mutability" => "all"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                false,
            ],
            [
                ["visibility" => "public", "mutability" => "restricted"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "public", "mutability" => "none"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "private", "mutability" => "all"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                false,
            ],
            [
                ["visibility" => "private", "mutability" => "restricted"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "private", "mutability" => "none"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "internal", "mutability" => "all"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "internal", "mutability" => "restricted"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["visibility" => "internal", "mutability" => "none"],
                ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED],
                true,
            ],
            [
                ["dataType" => "text", "formType" => "dropdown", "dropdownOptions" => ["one", "two"]],
                ["dropdownOptions" => ["x", "y", "z"]],
                false,
            ],
            [
                ["dataType" => "text", "formType" => "dropdown", "dropdownOptions" => ["one", "two"]],
                ["dropdownOptions" => ["x", 1, "x"]],
                true,
            ],
            [
                ["dataType" => "string[]", "formType" => "dropdown", "dropdownOptions" => ["one", "two"]],
                ["dropdownOptions" => ["x", "y", "z"]],
                false,
            ],
            [
                ["dataType" => "string[]", "formType" => "dropdown", "dropdownOptions" => ["one", "two"]],
                ["dropdownOptions" => ["x", true, "z"]],
                true,
            ],
            [
                ["dataType" => "number", "formType" => "dropdown", "dropdownOptions" => [1, 2]],
                ["dropdownOptions" => [1, 2, 3]],
                false,
            ],
            [
                ["dataType" => "number", "formType" => "dropdown", "dropdownOptions" => [1, 2]],
                ["dropdownOptions" => [1, "two", 3]],
                true,
            ],
            [
                ["dataType" => "number[]", "formType" => "dropdown", "dropdownOptions" => [1, 2]],
                ["dropdownOptions" => [1, 2, 3]],
                false,
            ],
            [
                ["dataType" => "number[]", "formType" => "dropdown", "dropdownOptions" => [1, 2]],
                ["dropdownOptions" => [1, false, 3]],
                true,
            ],
        ];
    }

    /**
     * Combines test data from providePatchData and adds some post-only test data
     *
     * @return array
     */
    public function providePostData(): array
    {
        $record = $this->record();
        $tests = [];
        foreach ($this->providePatchData() as $data) {
            [$postData, $patchData, $expectException] = $data;
            $tests[] = [$patchData + $postData + $this->record(), $expectException];
        }

        $tests["Invalid apiName with whitespace"] = [["apiName" => " test "] + $record, true];
        $tests["Invalid apiName with periods"] = [["apiName" => "te.st"] + $record, true];
        $tests["Valid apiName"] = [["apiName" => "test"] + $record, false];
        $tests["Missing apiName"] = [array_diff_key($record, ["apiName" => 1]), true];
        $tests["Missing label"] = [array_diff_key($record, ["label" => 1]), true];
        $tests["Missing description"] = [array_diff_key($record, ["description" => 1]), false];
        $tests["Missing dataType"] = [array_diff_key($record, ["dataType" => 1]), true];
        $tests["Missing formType"] = [array_diff_key($record, ["formType" => 1]), true];
        $tests["Missing visibility"] = [array_diff_key($record, ["visibility" => 1]), true];
        $tests["Missing mutability"] = [array_diff_key($record, ["mutability" => 1]), true];
        $tests["Missing displayOptions"] = [array_diff_key($record, ["displayOptions" => 1]), true];
        $tests["Missing registrationOptions"] = [
            array_diff_key($record, ["registrationOptions" => ProfileFieldModel::REGISTRATION_REQUIRED]),
            false,
        ];
        $tests["Missing sort"] = [array_diff_key($record, ["sort" => 1]), false];

        return $tests;
    }

    /**
     * Tests that each new profile field has an auto-generated sort value of 1 + the max sort value
     * and that the PUT /profile-fields/sorts endpoint updates sort values using a apiName => sort mapping
     *
     * @return void
     */
    public function testSort()
    {
        $this->api()->post($this->baseUrl, ["apiName" => "one", "label" => "one"] + $this->record());
        $this->api()->post($this->baseUrl, ["apiName" => "two", "label" => "two"] + $this->record());
        $this->api()->post($this->baseUrl, ["apiName" => "three", "label" => "three"] + $this->record());

        $profileFields = $this->api()->get($this->baseUrl);
        [$profileField1, $profileField2, $profileField3] = $profileFields;
        $this->assertSame(1, $profileField1["sort"]);
        $this->assertSame(2, $profileField2["sort"]);
        $this->assertSame(3, $profileField3["sort"]);

        $this->api()->put("$this->baseUrl/sorts", ["one" => 10, "two" => 5, "three" => 15]);

        $profileFields = $this->api()->get($this->baseUrl);
        [$profileField1, $profileField2, $profileField3] = $profileFields;
        $this->assertSame(5, $profileField1["sort"]);
        $this->assertSame(10, $profileField2["sort"]);
        $this->assertSame(15, $profileField3["sort"]);

        // Test permission error (403)
        $this->runWithUser(function () {
            $this->runWithExpectedExceptionCode(403, function () {
                $this->api()->put("$this->baseUrl/sorts", ["one" => 10, "two" => 5, "three" => 15]);
            });
        }, \UserModel::GUEST_USER_ID);
    }

    /**
     * Tests that not found and permission exceptions are thrown correctly.
     *
     * @return void
     */
    public function testDeleteFailed()
    {
        $this->testPost(null, ["apiName" => "deletion_test", "label" => "deletion_test"]);

        // Test permission error (403)
        $this->runWithUser(function () {
            $this->runWithExpectedExceptionCode(403, function () {
                $this->api()->delete("$this->baseUrl/deletion_test");
            });
        }, \UserModel::GUEST_USER_ID);

        // Test not found error (404)
        $this->runWithExpectedExceptionCode(404, function () {
            $this->api()->delete("$this->baseUrl/doesnt_exist");
        });
    }

    /**
     * Tests that calling the index endpoint without correct permissions throws a permission exception.
     *
     * @return void
     */
    public function testIndexFailed()
    {
        $this->runWithUser(function () {
            $this->runWithExpectedExceptionCode(403, [$this, "testIndex"]);
        }, \UserModel::GUEST_USER_ID);
    }

    /**
     * Test that when we change a profile field's formType from "dropdown" to something else, the dropdown options are removed.
     */
    public function testDropdownOptionsRemoved(): void
    {
        // Set up the initial record the way we want it.
        $initialRecord = $this->record;
        $initialRecord["formType"] = "dropdown";
        $initialRecord["dropdownOptions"] = ["one", "two"];

        // Post it.
        $initialRecord = $this->testPost($initialRecord);

        // Patch it.
        $patchedRecord = $this->api()
            ->patch("{$this->baseUrl}/{$initialRecord["apiName"]}", ["formType" => ProfileFieldModel::FORM_TYPE_TEXT])
            ->getBody();
        $this->assertNull($patchedRecord["dropdownOptions"]);
    }

    /**
     * Test that when we patch a dropdown type profile field's dropdown options, they are replaced, not merged with the existing ones.
     */
    public function testDropdownOptionsReplaced(): void
    {
        // Set up the initial record the way we want it.
        $initialRecord = $this->record;
        $initialRecord["formType"] = "dropdown";
        $initialRecord["dropdownOptions"] = ["one", "two"];

        // Post it.
        $initialRecord = $this->testPost($initialRecord);

        // Patch it.
        $patchOptions = ["three", "four"];
        $patchedRecord = $this->api()
            ->patch("{$this->baseUrl}/{$initialRecord["apiName"]}", ["dropdownOptions" => $patchOptions])
            ->getBody();

        // It should only have the dropdownOptions we specified in the patch. The original options should have been replaced.
        $this->assertEqualsCanonicalizing($patchOptions, $patchedRecord["dropdownOptions"]);
    }

    /**
     * @inheritDoc
     */
    protected function generateIndexRows(): array
    {
        $rows = [];

        // Insert a few rows.
        for ($i = 0; $i < static::INDEX_ROWS; $i++) {
            $record = ["apiName" => "profile-field-test" . $i, "label" => "profile field test" . $i] + $this->record();
            $rows[] = $this->testPost($record);
        }

        return $rows;
    }
}
