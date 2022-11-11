/**
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license Proprietary
 */

import {
    FetchProfileFieldsParams,
    FetchUserProfileFieldsParams,
    PatchProfileFieldParams,
    PostProfileFieldParams,
    ProfileField,
    UserProfileFields,
} from "@dashboard/userProfiles/types/UserProfiles.types";
import apiv2 from "@library/apiv2";
import { createAsyncThunk } from "@reduxjs/toolkit";
import { RecordID } from "@vanilla/utils";

const API_ENDPOINT = "/profile-fields";

export const fetchProfileFields = createAsyncThunk<ProfileField[], FetchProfileFieldsParams>(
    "@@userProfiles/fetchProfileFields",
    async (params, { rejectWithValue }) => {
        try {
            const { data: profileFields } = await apiv2.get<ProfileField[]>(API_ENDPOINT, params);

            return profileFields;
        } catch (err) {
            return rejectWithValue(err);
        }
    },
);

export const fetchProfileField = createAsyncThunk<ProfileField, ProfileField["apiName"]>(
    "@@userProfiles/fetchProfileField",
    async (apiName, { rejectWithValue }) => {
        try {
            const { data: profileField } = await apiv2.get<ProfileField>(`${API_ENDPOINT}/${apiName}`);
            return profileField;
        } catch (err) {
            return rejectWithValue(err);
        }
    },
);

export const postProfileField = createAsyncThunk<ProfileField, PostProfileFieldParams>(
    "@@userProfiles/postProfileField",
    async (params, { rejectWithValue }) => {
        try {
            const { data: profileField } = await apiv2.post<ProfileField>(API_ENDPOINT, params);
            return profileField;
        } catch (err) {
            return rejectWithValue(err);
        }
    },
);

export const patchProfileField = createAsyncThunk<ProfileField, PatchProfileFieldParams>(
    "@@userProfiles/patchProfileField",
    async ({ apiName, ...params }, { rejectWithValue }) => {
        try {
            const { data: profileField } = await apiv2.patch<ProfileField>(`${API_ENDPOINT}/${apiName}`, params);
            return profileField;
        } catch (err) {
            return rejectWithValue(err);
        }
    },
);

export const fetchUserProfileFields = createAsyncThunk<UserProfileFields, FetchUserProfileFieldsParams>(
    "@@userProfiles/fetchUserProfileFields",
    async ({ userID, ...params }, { rejectWithValue }) => {
        try {
            const { data: userProfileFields } = await apiv2.get<UserProfileFields>(
                `users/${userID}${API_ENDPOINT}`,
                params,
            );

            return userProfileFields;
        } catch (error) {
            return rejectWithValue(error);
        }
    },
);
