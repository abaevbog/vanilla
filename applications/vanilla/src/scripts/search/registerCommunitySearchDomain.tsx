/**
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React from "react";
import { SearchFormContextProvider } from "@vanilla/library/src/scripts/search/SearchFormContext";
import { TypeDiscussionsIcon } from "@vanilla/library/src/scripts/icons/searchIcons";
import { ISearchForm } from "@vanilla/library/src/scripts/search/searchTypes";
import { ICommunitySearchTypes } from "@vanilla/addon-vanilla/search/communitySearchTypes";
import { SearchFilterPanelDiscussions } from "@vanilla/addon-vanilla/search/FilterPanelDiscussions";
import { t } from "@vanilla/i18n";
import { onReady } from "@vanilla/library/src/scripts/utility/appUtils";

export function registerCommunitySearchDomain() {
    onReady(() => {
        SearchFormContextProvider.addPluggableDomain({
            key: "discussions",
            name: t("Discussions"),
            icon: <TypeDiscussionsIcon />,
            getAllowedFields: () => {
                return [
                    // "tags", Not implemented for now.
                    "categoryID",
                    "followedCategories",
                    "includeChildCategories",
                    "includeArchivedCategories",
                ];
            },
            transformFormToQuery: (form: ISearchForm<ICommunitySearchTypes>) => {
                const query = {
                    ...form,
                };

                if (query.categoryOption) {
                    query.categoryID = query.categoryOption.value as number;
                }
                return query;
            },
            getRecordTypes: () => {
                return ["discussion"];
            },
            PanelComponent: SearchFilterPanelDiscussions,
            getDefaultFormValues: () => {
                return {
                    followedCategories: false,
                    includeChildCategories: false,
                    includeArchivedCategories: false,
                };
            },
        });
    });
}
