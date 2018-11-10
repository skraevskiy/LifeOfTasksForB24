(function($) {
	let lifeOfTasks = function(data) {
			$('body').html('Загрузка');
			let loader = setInterval(function() { $('body').append('.'); }, 500);

			$.ajax({
				type: "POST",
				data: data,
				url: 'LifeOfTasks.php',
				success: function(data){
					clearInterval(loader);
					$('body').html(data);
					lifeOfTasksEvents();
				}
			});
		},
		lifeOfTasksEvents = function() {
			if($('div.lifeOfTasks select').is('[name="staff_list"]')) {
				let selectStaff = $('div.lifeOfTasks select[name="staff_list"]'),
					buttonUpdate = $('div.lifeOfTasks button[name="staff_list"]');

				selectStaff.change(function() {
					let selectedUserId = parseInt($(this).val());
					if (selectedUserId > 0) {
						lifeOfTasks("selectedUserId=" + selectedUserId);
					}
				});

				buttonUpdate.click(function() {
					let selectedUserId = parseInt(selectStaff.val());
					if (selectedUserId > 0) {
						lifeOfTasks("selectedUserId=" + selectedUserId);
					}
				});
			}
		};

	lifeOfTasks();
}(jQuery));