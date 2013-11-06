function showBoxes() {
	var user_function = $('#api_function').val();
	
	$('.group_fields').hide();
	switch (user_function) {
		case "1_1":	
		case "1_2":
		case "9_2":
		case "12_1":
		case "17_2":
		case "18_2":
		case "20_1":
		case "20_2":
			$('#user_details').show();
			break;
		case "2_1":
		case "2_2":
		case "2_3":
		case "2_4":
		case "3_1":
		case "3_2":
		case "19_2":
		case "19_3":
		case "19_4":
		case "19_5":
			$('#user_details').show();
			$('#class_details').show();
			break;
		case "4_1":
		case "4_2":
		case "4_3":
		case "4_4":
		case "4_5":
		case "4_7":
		case "10_1":
		case "10_2":
		case "11_2":
			$('#user_details').show();
			$('#class_details').show();
			$('#assignment_details').show();
			break;
		case "5_1":
		case "5_2":
		case "6_1":
		case "6_2":
		case "7_1":
		case "7_2":
		case "8_2":
			$('#user_details').show();
			$('#class_details').show();
			$('#assignment_details').show();
			$('#submission_details').show();
			break;
		case "13_1":
		case "13_2":
		case "13_4":
		case "15_2":
		case "15_3":
			$('#user_details').show();
			$('#submission_details').show();
			break;
		case "13_3":
			$('#user_details').show();
			$('#class_details').show();
			$('#assignment_details').show();
		case "14_2":
			$('#user_details').show();
			$('#misc_details').show();
			break;
		case "21_2":
			$('#user_details').show();
			$('#assignment_details').show();
			$('#misc_details').show();
			break;
	}
}