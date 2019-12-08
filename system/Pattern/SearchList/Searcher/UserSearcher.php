<?php

namespace CodeHuiter\Pattern\SearchList\Searcher;

use CodeHuiter\Pattern\Module\Auth\AuthService;
use CodeHuiter\Pattern\Module\Auth\Model\UserModel;
use CodeHuiter\Pattern\SearchList\MultiTableSearcher\MultiTableSearcher;
use CodeHuiter\Pattern\SearchList\SearchListResult;
use CodeHuiter\Service\DateService;

class UserSearcher extends MultiTableSearcher
{
    public function search(
        array $options = [],
        array $filters = [],
        array $pages = [],
        bool $requireResultCount = false
    ): SearchListResult {
        $model = new UserModel();
        $userTable = $model->getModelTable();

        $this->sqls_table = $userTable;
        $this->sqls_extend = ['data_info'];
        $this->sqls_one = false;
        $this->sqls_connect  = array();
        $this->sqls_select = " $userTable.* ";
        $this->sqls_from = $userTable;
        $this->sqls_where = " 1 ";
        $this->sqls_order = " ORDER BY $userTable.lastact DESC ";

        if ($filters){
            if ($filters['query'] ?? '') {
                $this->sqls_where .= " AND (
						$userTable.name LIKE :{$this->sqls_table}_like_name
						OR $userTable.login LIKE :{$this->sqls_table}_like_login
						OR $userTable.firstname LIKE :{$this->sqls_table}_like_firstname
						OR $userTable.lastname LIKE :{$this->sqls_table}_like_lastname
						OR {$this->sqls_table}.alias = :{$this->sqls_table}_alias
					) ";
                $this->bindings[":{$this->sqls_table}_like_name"] = "%{$filters['query']}%";
                $this->bindings[":{$this->sqls_table}_like_login"] = "%{$filters['query']}%";
                $this->bindings[":{$this->sqls_table}_like_firstname"] = "%{$filters['query']}%";
                $this->bindings[":{$this->sqls_table}_like_lastname"] = "%{$filters['query']}%";
                $this->bindings[":{$this->sqls_table}_alias"] = $filters['query'];
            }
            if ($filters['show'] ?? ''){
                if ($filters['show'] === 'random'){
                    $this->sqls_order =  " ORDER BY RAND() ";
                    $requireResultCount = false;
                }
                if ($filters['show'] === 'last'){

                }
                if ($filters['show'] === 'online'){
                    $lastactborder = $this->getDateService()->getCurrentTimestamp() - $this->application->config->authConfig->onlineTime;
                    $this->sqls_where .= " AND users.lastact > $lastactborder ";
                }
                if ($filters['show'] === 'moderators'){
                    $this->sqls_where .= " AND users.groups LIKE :{$this->sqls_table}_like_groups";
                    $moderatorGroup = AuthService::GROUP_MODERATOR;
                    $this->bindings[":{$this->sqls_table}_like_groups"] = "%$moderatorGroup%";
                }
            }
//			if ($this->mm->g($filters['going_tour'])){
//				$this->sqls_from .= " LEFT JOIN user_geo ON user_geo.user_id = users.id AND user_geo.geo_type = 'tour_going' AND user_geo.object_id = {$this->mm->sqlInt($filters['going_tour'])} ";
//				$this->sqls_where .= " AND user_geo.object_id = '{$this->mm->sqlInt($filters['going_tour'],0)}' ";
//				$sqlWhereRequire = '';
//			}
        }

        return $this->searchFinish(UserModel::class, $options, $filters, $pages, $requireResultCount);
    }

    private function getDateService(): DateService
    {
        return $this->application->get(DateService::class);
    }
}