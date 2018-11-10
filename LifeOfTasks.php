<?php
class lifeOfTasks {
	private static $domain = '';
	private static $adminId = '';
	private static $tokenIn = '';
	private static $url = '';

	private static $selectedUserId = '';

	private static $totalTasksPending = 0;
	private static $totalTasksProgress = 0;
	private static $totalTasksCompleted = 0;
	private static $totalTasksCompleted30d = 0;
	private static $totalTasksCompleted7d = 0;
	private static $totalTasksCompleted30dTime = 0;
	private static $totalTasksCompleted30dAvgTime = 0;
	private static $totalTasksCompletedFreeTime = 0;

	function __construct($domain, $adminId, $tokenIn) {
		if (empty($domain) || empty($adminId) || empty($tokenIn)) exit;

		self::$domain = $domain;
		self::$adminId = $adminId;
		self::$tokenIn = $tokenIn;
		self::$url = 'https://'.$domain.'/rest/'.$adminId.'/'.$tokenIn.'/';

		if (!empty($_REQUEST['selectedUserId'])) self::$selectedUserId = $_REQUEST['selectedUserId'];
		self::ui();
	}

	private function response($method, $data = array()) {
		$url = self::$url;
		if (empty($url) || empty($method) || (!empty($data) && !is_array($data))) return false;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_URL, $url.$method);

		if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

		$response = curl_exec($curl);
		curl_close($curl);

		return !empty($response) ? json_decode($response, true) : false;
	}

	private function userSelectedStatus() {
		if (empty(self::$selectedUserId)) return false;

		switch ($status = self::response('timeman.status.json', array('USER_ID' => self::$selectedUserId))['result']['STATUS']) {
			case 'CLOSED':
				$status = 'Не работает';
			break;
			case 'OPENED':
				$status = 'Работает';
			break;
			case 'PAUSED':
				$status = 'На перерыве';
			break;
			default: $status = '';
		}

		return !empty($status) ? $status : false;
	}

	private function staffList() {
		$data = array(
			'sort' => 'LAST_NAME',
			'order' => 'ASC',
			'FILTER' => array(
				'ACTIVE' => '1'
			),
		);

		$arr = self::response('user.get.json', $data)['result'];
		if (count($arr) <= 0) return false;

		foreach ($arr as $item) {
			$selected = self::$selectedUserId == $item['ID'] ? ' selected' : '';
			$list .= '<option' . $selected . ' value="' . $item['ID'] . '">' . (!empty($item['LAST_NAME']) ? $item['LAST_NAME'] . ' ' : '') . $item['NAME'] . (!empty($selected) ? ' (' . self::userSelectedStatus() . ')' : '') . '</option>';
		}

		return (!empty($list) ? '<select name="staff_list"><option' . (empty(self::$selectedUserId) ? ' selected' : '') . ' disabled value="">Выберите сотрудника</option>' . $list . '</select>' . (!empty(self::$selectedUserId) ? ' <button name="staff_list">Обновить</button>' : '') : false);
	}

	private function tasksList($status = '', $page = 1) {
		if (empty(self::$selectedUserId)) return false;

		$data = array();

		$data['ORDER'] = array(
			'DEADLINE' => 'desc',
			'CREATED_DATE' => 'desc',
			'TITLE' => 'asc',
		);

		$data['FILTER'] = array(
			'RESPONSIBLE_ID' => self::$selectedUserId,
		);

		switch ($status) {
			case 'pending':
				$data['FILTER']['!REAL_STATUS'] = array(3, 4, 5, 6, 7);
			break;
			case 'progress':
				$data['FILTER']['REAL_STATUS'] = array(3);
			break;
			case 'completed':
				$data['FILTER']['REAL_STATUS'] = array(4, 5);
			break;
			default:
				$data['FILTER']['!REAL_STATUS'] = array(4, 5, 6, 7);
			break;
		}

		$data['PARAMS']['NAV_PARAMS'] = array(
			'nPageSize' => 50,
			'iNumPage' => $page,
		);

		$data['SELECT'] = array(
			'TITLE',
			'DEADLINE',
			'PRIORITY',
			'RESPONSIBLE_ID',
			'ID',
			'CREATED_BY',
			'REAL_STATUS',
			'STATUS',
			'RESPONSIBLE_NAME',
			'RESPONSIBLE_LAST_NAME',
			'DATE_START',
			'DURATION_FACT',
			'CREATED_BY_NAME',
			'CREATED_BY_LAST_NAME',
			'CREATED_DATE',
			'CLOSED_DATE',
		);

		$arr = self::response('task.item.list.json', $data);
		if (count($arr['result']) <= 0) return false;

		foreach ($arr['result'] as $item) {
			$list .= '<div id="' . $item['ID'] . '" class="task_item">
				<div class="task_title">' . $item['ID'] . ' &mdash; ' . $item['TITLE'] . '</div>
				<div class="task_description">
					' . ($item['DURATION_FACT'] > 0 ? '<p><b>Затрачено времени:</b> ' . $item['DURATION_FACT'] . ' мин.' . ($item['DURATION_FACT'] > 60 ? ' ~ ' . round($item['DURATION_FACT']/60) . ' ч.' : '') . '</p>' : '') . '
					' . ((strtotime($item['DEADLINE']) > strtotime('01.01.1970')) ? '<p><b>Срок:</b> ' . date("d.m.Y G:i:s", strtotime($item['DEADLINE'])) . '</p>' : '') . '
					' . ((strtotime($item['DATE_START']) > strtotime('01.01.1970')) ? '<p><b>Начало выполенения:</b> ' . date("d.m.Y G:i:s", strtotime($item['DATE_START'])) . '</p>' : '') . '
					' . ((strtotime($item['CREATED_DATE']) > strtotime('01.01.1970')) ? '<p><b>Дата постановки:</b> ' . date("d.m.Y G:i:s", strtotime($item['CREATED_DATE'])) . '</p>' : '') . '
					<p><b>Постановщик:</b> ' . $item['CREATED_BY_NAME'] . ' ' . $item['CREATED_BY_LAST_NAME'] . '</p>
				</div>
			</div>';

			if($status == 'completed') {
				if (strtotime($item['CLOSED_DATE']) > (time()-(30*24*60*60))) {
					self::$totalTasksCompleted30d++;
					self::$totalTasksCompleted30dTime += (int)$item['DURATION_FACT'];
				}

				if (strtotime($item['CLOSED_DATE']) > (time()-(7*24*60*60))) self::$totalTasksCompleted7d++;
			}
		}

		if(!empty($list)) {
			switch ($status) {
				case 'pending':
					self::$totalTasksPending = $arr['total'];
				break;
				case 'progress':
					self::$totalTasksProgress = $arr['total'];
				break;
				case 'completed':
					self::$totalTasksCompleted = $arr['total'];

					if (self::$totalTasksCompleted30dTime > 0) self::$totalTasksCompleted30dAvgTime = round(self::$totalTasksCompleted30dTime/self::$totalTasksCompleted30d);
				break;
			}
		}

		return !empty($list) ? $list : false;
	}

	private function staffTasksInfoList() {
		if (empty(self::$selectedUserId)) return false;

		$tasksCompletedList = self::tasksList('completed');

		if (self::$totalTasksCompleted30dAvgTime > 0 && (self::$totalTasksPending+self::$totalTasksProgress) > 0) self::$totalTasksCompletedFreeTime = round(self::$totalTasksCompleted30dAvgTime*(self::$totalTasksPending+self::$totalTasksProgress));

		$list .= self::$totalTasksCompletedFreeTime > 0 ? '<div style="opacity: .6;" class="task_item" title="Beta-версия"><b>Будет свободен, через:</b> ' . self::$totalTasksCompletedFreeTime . ' мин.' . (self::$totalTasksCompletedFreeTime > 60 ? ' ~ ' . round(self::$totalTasksCompletedFreeTime/60) .  ' ч.' : '') . '</div>' : '';
		$list .= self::$totalTasksCompleted30dAvgTime > 0 ? '<div class="task_item"><b>Среднее время выполнения задачи:</b> ' . self::$totalTasksCompleted30dAvgTime . ' мин.' . (self::$totalTasksCompleted30dAvgTime > 60 ? ' ~ ' . round(self::$totalTasksCompleted30dAvgTime/60) . ' ч.' : '') . '</div>' : '';
		$list .= self::$totalTasksPending > 0 ? '<div class="task_item"><b>В очереди:</b> ' . self::$totalTasksPending . ' задач</div>' : '';
		$list .= self::$totalTasksProgress > 0 ? '<div class="task_item"><b>На выполнении:</b> ' . self::$totalTasksProgress . ' задач</div>' : '';
		$list .= self::$totalTasksCompleted7d > 0 ? '<div class="task_item"><b>Выполнено за 7 дней:</b> ' . self::$totalTasksCompleted7d . ' задач</div>' : '';
		$list .= self::$totalTasksCompleted30d > 0 ? '<div class="task_item"><b>Выполнено за 30 дней:</b> ' . self::$totalTasksCompleted30d . ' задач</div>' : '';
		$list .= self::$totalTasksCompleted > 0 ? '<div class="task_item"><b>Выполнено:</b> ' . self::$totalTasksCompleted . ' задач</div>' : '';

		return !empty($list) ? $list : false;
	}

	private function ui() {
		$staffList = self::staffList();
		if (empty($staffList)) return false;

		$html .= '<div class="staff_list"><b>Список сотрудников:</b> ' . $staffList . '</div>';

		$tasksList = self::tasksList('pending');
		$tasksProgressList = self::tasksList('progress');

		if (empty($tasksList) && empty($tasksProgressList) && !empty(self::$selectedUserId)) {
			$html .= '<p>У выбранного сотрудника отсутсвуют задачи.</p>';
		} elseif (!empty(self::$selectedUserId)) {
			$html .= '<div class="tasks_container">';

			$html .= !empty($tasksList) ? '<div class="tasks_list"><h2 class="title">Задачи в очереди</h2>' . $tasksList . '</div>' : '';
			$html .= !empty($tasksProgressList) ? '<div class="tasks_list"><h2 class="title">Задачи на выполнении</h2>' . $tasksProgressList . '</div>' : '';

			$staffTasksInfoList = self::staffTasksInfoList();
			$html .= !empty($staffTasksInfoList) ? '<div class="tasks_list"><h2 class="title">Продуктивность</h2>' . $staffTasksInfoList . '</div>' : '';

			$html .= '</div>';
		}

		echo !empty($html) ? '<div class="lifeOfTasks">' . $html . '</div>' : '';
	}

	private function __clone() {}
	private function __wakeup() {}
}

@new lifeOfTasks();