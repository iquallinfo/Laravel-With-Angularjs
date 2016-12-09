<?php

class OnlineExamsController extends \BaseController {

	var $data = array();
	var $panelInit ;
	var $layout = 'dashboard';

	public function __construct(){
		$this->panelInit = new \DashboardInit();
		$this->data['panelInit'] = $this->panelInit;
		$this->data['breadcrumb']['Settings'] = \URL::to('/dashboard/languages');
		$this->data['users'] = \Auth::user();

		if(!$this->data['users']->hasThePerm('onlineExams')){
			exit;
		}
	}

	public function listAll()
	{
		$toReturn = array();
		$toReturn['classes'] = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
		$classesArray = array();
		while (list(, $class) = each($toReturn['classes'])) {
			$classesArray[$class['id']] = array("classTitle"=>$class['className'],"subjects"=>json_decode($class['classSubjects']));
		}

		if($this->data['users']->role == "teacher"){
			$subjects = subject::where('teacherId','LIKE','%'.$this->data['users']->id.'%')->get()->toArray();	
//                        echo "<pre>";
//                        print_r($subjects);
//                        exit;
		}else{
			$subjects = subject::get()->toArray();
		}
		$subjectArray = array();
		while (list(, $subject) = each($subjects)) {
			$subjectArray[$subject['id']] = $subject['subjectTitle'];
		}

		$toReturn['onlineExams'] = array();
		$onlineExams = new onlineExams();

		if($this->data['users']->role == "teacher"){
			$onlineExams = $onlineExams->get();
		}

		if($this->data['users']->role == "student"){
			//$onlineExams = $onlineExams->where('examClass','LIKE','%"'.$this->data['users']->studentClass.'"%');
			$onexids = $this->selectExams();
                        
			if(count($onexids) > 0)
			{
				$onlineExams = $onlineExams->whereIn('id',$onexids)->get();
			}
		}
		if($this->data['users']->role == "admin"){
			//$onlineExams = $onlineExams->where('exAcYear',$this->panelInit->selectAcYear);
			$onlineExams = $onlineExams->get();
		}
		if(count($onlineExams) <=0)
		{
			return $this->panelInit->apiOutput(false,$this->panelInit->language['delExam'],$this->panelInit->language['exNotExist']);
		}
		
               
                       
			foreach ($onlineExams as $key => $onlineExam) {
			$classId = json_decode($onlineExam->examClass);
			if($this->data['users']->role == "student" AND !in_array($this->data['users']->studentClass, $classId)){
				continue;
			}
			$toReturn['onlineExams'][$key]['id'] = $onlineExam->id;
			$toReturn['onlineExams'][$key]['examTitle'] = $onlineExam->examTitle;
			$toReturn['onlineExams'][$key]['examDescription'] = $onlineExam->examDescription;
                        
			if(isset($subjectArray[$onlineExam->examSubject])){
				$toReturn['onlineExams'][$key]['examSubject'] = $subjectArray[$onlineExam->examSubject];
			}
                        if($onlineExam->ExamEndDate !="")
                        {
                            $toReturn['onlineExams'][$key]['ExamEndDate'] = date("F j, Y",$onlineExam->ExamEndDate);
                        }
                        $yearLevel ="";
                        $ylevel= yearlevel::where('id',$onlineExam->yearlevel)->get()->toArray();
                        if(count($ylevel) > 0)
                        {
                            $yearLevel = $ylevel[0]["title"];
                        }
                        $toReturn['onlineExams'][$key]['yearlevel'] =$yearLevel;
			$toReturn['onlineExams'][$key]['ExamShowGrade'] = $onlineExam->ExamShowGrade;
                        $gLevel= gradelevels::where('id',$onlineExam->gradelevel)->get()->toArray();
                        if(count($gLevel) > 0)
                        {
                            $toReturn['onlineExams'][$key]['gradelevel'] = $gLevel[0]["gradeName"];
                        }
                         
                        $toReturn['onlineExams'][$key]['tag'] = $onlineExam->tag;
                        
			$toReturn['onlineExams'][$key]['classes'] = "";

			while (list(, $value) = each($classId)) {
				if(isset($classesArray[$value])){
					$toReturn['onlineExams'][$key]['classes'] .= $classesArray[$value]['classTitle'].", ";
				}
			}
		}
                 
                $gradelevels = gradelevels::get()->toArray();
                $toReturn['gradelevels'] = $gradelevels;
                
                $allsubjects = subject::get()->toArray();
                $toReturn['allsubjects'] = $allsubjects;
                
                $yearlevels = yearlevel::get()->toArray();
                $toReturn['yearlevels'] = $yearlevels;
		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
	}

	public function delete($id){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		if ( $postDelete = onlineExams::where('id', $id)->first() )
        {
            $postDelete->delete();
            return $this->panelInit->apiOutput(true,$this->panelInit->language['delExam'],$this->panelInit->language['exDeleted']);
        }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['delExam'],$this->panelInit->language['exNotExist']);
        }
	}

	public function create(){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		$onlineExams = new onlineExams();
		$onlineExams->examTitle = Input::get('examTitle');
		$onlineExams->examDescription = Input::get('examDescription');
		$onlineExams->examClass = json_encode(Input::get('examClass'));
		$onlineExams->examTeacher = $this->data['users']->id;
		$onlineExams->examSubject = Input::get('examSubject');
		$onlineExams->examDate = strtotime(Input::get('examDate'));
		$onlineExams->exAcYear = $this->panelInit->selectAcYear;
		$onlineExams->ExamEndDate = strtotime(Input::get('ExamEndDate'));
		if(Input::has('ExamShowGrade')){
			$onlineExams->ExamShowGrade = Input::get('ExamShowGrade');
		}
		$onlineExams->examTimeMinutes = Input::get('examTimeMinutes');
		$onlineExams->examDegreeSuccess = Input::get('examDegreeSuccess');
                $onlineExams->gradelevel = Input::get('gradelevel');
		$onlineExams->yearlevel = Input::get('yearlevel');
		$onlineExams->examQuestion = json_encode(Input::get('examQuestion'));
                $onlineExams->tag = Input::get('tag');
		$onlineExams->save();
                $onlineExams->ExamEndDate = date("F j, Y",$onlineExams->ExamEndDate);
                
                $yearLevel ="";
                $ylevel= yearlevel::where('id',$onlineExams->yearlevel)->get()->toArray();
                if(count($ylevel) > 0){
                    $yearLevel = $ylevel[0]["title"];
                }
                $onlineExams->yearlevel = $yearLevel;

                $glevel= gradelevels::where('id',$onlineExams->gradelevel)->get()->toArray();
                if(count($glevel) > 0){
                    $gradeName = $glevel[0]["gradeName"];
                }
                $onlineExams->gradelevel = $gradeName;
                
                $subjectData = subject::where('id',$onlineExams->examSubject)->get()->toArray();
                if(count($subjectData) >0)
                {
                    $onlineExams->examSubject = $subjectData[0]["subjectTitle"];
                }
                else
                {
                    $onlineExams->examSubject = "";
                }
                
		return $this->panelInit->apiOutput(true,$this->panelInit->language['addExam'],$this->panelInit->language['examCreated'],$onlineExams->toArray() );
	}

	function fetch($id){
		$istook = onlineExamsGrades::where('examId',$id)->where('studentId',$this->data['users']->id)->count();

		$onlineExams = onlineExams::where('id',$id)->first()->toArray();
		$onlineExams['examClass'] = json_decode($onlineExams['examClass']);
		$onlineExams['examQuestion'] = json_decode($onlineExams['examQuestion']);
		if(time() > $onlineExams['ExamEndDate'] || time() < $onlineExams['examDate']){
			$onlineExams['finished'] = true;
		}
		if($istook > 0){
			$onlineExams['taken'] = true;
		}
                if($onlineExams['examDate'] != "")
                {
                    $onlineExams['examDate'] = date("m/d/Y",$onlineExams['examDate']);
                }
                if($onlineExams['ExamEndDate'] != "")
                {
                    $onlineExams['ExamEndDate'] = date("m/d/Y",$onlineExams['ExamEndDate']);
                }
                $gradelevels = \DB::table('classgradelevels')
                                ->leftJoin('gradelevels', 'gradelevels.id', '=', 'classgradelevels.level')
                                ->select('gradelevels.id as gid',
                                        'classgradelevels.*',
                                        'classgradelevels.id as cgid',
                                        'gradelevels.gradeName as gradeName')
                                ->whereIn("classgradelevels.class",$onlineExams['examClass'])
                                ->groupBy('gradelevels.id')
                                ->get();
                $onlineExams['gradelevels'] = $gradelevels;
                
		$DashboardController = new DashboardController();
		$onlineExams['subject'] = $DashboardController->subjectList($onlineExams['examClass']);
                
                $classList = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
                $selClasses = $onlineExams['examClass'];
                $finalclass = array();
                if($selClasses != ""){
                foreach($classList as $cl){
                        if(in_array($cl['id'],$selClasses))
                        {
                                $finalclass[]=array(
                                        "id" =>$cl['id'],
                                        "className" =>$cl['className'],
                                        "selected"=>"selected",
                                );
                        }
                        else
                        {
                                $finalclass[]=array(
                                        "id" =>$cl['id'],
                                        "className" =>$cl['className'],
                                        "selected"=>"",
                                );
                        }
                }
                $onlineExams["classes"] = $finalclass;
                }
                else
                {
                        $classList = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
                        $onlineExams["classes"] = $classList;
                }
                
		return $onlineExams;
	}

	function marks($id){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		$return = array();

		$exam = onlineExams::where('id',$id)->first();
		$return['examDegreeSuccess'] = $exam->examDegreeSuccess;

		$return['grade'] = \DB::table('onlineExamsGrades')
					->where('examId',$id)
					->leftJoin('users', 'users.id', '=', 'onlineExamsGrades.studentId')
					->select('onlineExamsGrades.id as id',
					'onlineExamsGrades.examGrade as examGrade',
					'onlineExamsGrades.examDate as examDate',
					'users.fullName as fullName',
					'users.id as studentId')
					->get();

		return json_encode($return);
	}

	function edit($id){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		$onlineExams = onlineExams::find($id);
		$onlineExams->examTitle = Input::get('examTitle');
		$onlineExams->examDescription = Input::get('examDescription');
		$onlineExams->examClass = json_encode(Input::get('examClass'));
		$onlineExams->examTeacher = $this->data['users']->id;
		$onlineExams->examSubject = Input::get('examSubject');
		$onlineExams->examDate = strtotime(Input::get('examDate'));
		$onlineExams->ExamEndDate = strtotime(Input::get('ExamEndDate'));
		if(Input::has('ExamShowGrade')){
			$onlineExams->ExamShowGrade = Input::get('ExamShowGrade');
		}
		$onlineExams->examTimeMinutes = Input::get('examTimeMinutes');
		$onlineExams->examDegreeSuccess = Input::get('examDegreeSuccess');
		$onlineExams->examQuestion = json_encode(Input::get('examQuestion'));
                $onlineExams->gradelevel = Input::get('gradelevel');
		$onlineExams->yearlevel = Input::get('yearlevel');
                $onlineExams->tag = Input::get('tag');
		$onlineExams->save();
                $onlineExams->ExamEndDate = date("F j, Y",$onlineExams->ExamEndDate);
                
                $yearLevel ="";
                $ylevel= yearlevel::where('id',$onlineExams->yearlevel)->get()->toArray();
                if(count($ylevel) > 0){
                    $yearLevel = $ylevel[0]["title"];
                }
                $onlineExams->yearlevel = $yearLevel;

                $glevel= gradelevels::where('id',$onlineExams->gradelevel)->get()->toArray();
                if(count($glevel) > 0){
                    $gradeName = $glevel[0]["gradeName"];
                }
                $onlineExams->gradelevel = $gradeName;
                
                $subjectData = subject::where('id',$onlineExams->examSubject)->get()->toArray();
                if(count($subjectData) >0)
                {
                    $onlineExams->examSubject = $subjectData[0]["subjectTitle"];
                }
                else
                {
                    $onlineExams->examSubject = "";
                }
                $classList = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
                $onlineExams->classes = $classList;
		return $this->panelInit->apiOutput(true,$this->panelInit->language['editExam'],$this->panelInit->language['examModified'],$onlineExams->toArray() );
	}

	function take($id){
		$istook = onlineExamsGrades::where('examId',$id)->where('studentId',$this->data['users']->id);
		$istookFinish = $istook->first();
		$istook = $istook->count();
                
		if($istook == 0){
			$onlineExamsGrades = new onlineExamsGrades();
			$onlineExamsGrades->examId = $id;
			$onlineExamsGrades->studentId = $this->data['users']->id;
			$onlineExamsGrades->examDate = time() ;
			$onlineExamsGrades->save();
		}

		$onlineExams = onlineExams::where('id',$id)->first()->toArray();
		$onlineExams['examClass'] = json_decode($onlineExams['examClass']);
		$onlineExams['examQuestion'] = json_decode($onlineExams['examQuestion'],true);
                
                if(count($onlineExams['examQuestion']) == 0)
                {
                    return $this->panelInit->apiOutput(false,$this->panelInit->language['takeExam'],$this->panelInit->language['noquestionstoexam']);
                }
                    while (list($key, $value) = each($onlineExams['examQuestion'])) {
			if(isset($onlineExams['examQuestion'][$key]['Tans'])){
				unset($onlineExams['examQuestion'][$key]['Tans']);
			}
			if(isset($onlineExams['examQuestion'][$key]['Tans1'])){
				unset($onlineExams['examQuestion'][$key]['Tans1']);
			}
			if(isset($onlineExams['examQuestion'][$key]['Tans2'])){
				unset($onlineExams['examQuestion'][$key]['Tans2']);
			}
			if(isset($onlineExams['examQuestion'][$key]['Tans3'])){
				unset($onlineExams['examQuestion'][$key]['Tans3']);
			}
			if(isset($onlineExams['examQuestion'][$key]['Tans4'])){
				unset($onlineExams['examQuestion'][$key]['Tans4']);
			}
		}
		if(time() > $onlineExams['ExamEndDate'] || time() < $onlineExams['examDate']){
			$onlineExams['finished'] = true;
		}

		if($istook > 0 AND $istookFinish['examQuestionsAnswers'] != null){
			return $this->panelInit->apiOutput(false,$this->panelInit->language['takeExam'],$this->panelInit->language['exAlreadyTook']);
		}

		if($onlineExams['examTimeMinutes'] != 0 AND $istook > 0){
			if( (time() - $istookFinish['examDate']) > $onlineExams['examTimeMinutes']*60){
				return $this->panelInit->apiOutput(false,$this->panelInit->language['takeExam'],$this->panelInit->language['examTimedOut']);
			}
		}

		if($onlineExams['examTimeMinutes'] == 0){
			$onlineExams['timeLeft'] = 0;
		}else{
			if($istook == 0){
				$onlineExams['timeLeft'] = $onlineExams['examTimeMinutes'] * 60;
			}
			if($istook > 0){
				$onlineExams['timeLeft'] = $onlineExams['examTimeMinutes']*60 - (time() - $istookFinish['examDate']);
			}
		}

		$onlineExams['examDate'] = date("m/d/Y",$onlineExams['examDate']);
		$onlineExams['ExamEndDate'] = date("m/d/Y",$onlineExams['ExamEndDate']);
		return $onlineExams;
	}

	function took($id){
		$onlineExams = onlineExams::where('id',$id)->first()->toArray();
		$onlineExams['examQuestion'] = json_decode($onlineExams['examQuestion'],true);

		$toReturn = array();
		$answers = Input::get('examQuestion');
		$score = 0;
		while (list($key, $value) = each($answers)) {
			if( !isset($onlineExams['examQuestion'][$key]['type']) || (isset($onlineExams['examQuestion'][$key]['type']) AND $onlineExams['examQuestion'][$key]['type'] == "radio")){
				if($value['answer'] == $onlineExams['examQuestion'][$key]['Tans']){
					if(isset($onlineExams['examQuestion'][$key]['questionMark'])){
						$score += $onlineExams['examQuestion'][$key]['questionMark'];
					}else{
						$score++;
					}
				}
			}
			if(isset($onlineExams['examQuestion'][$key]['type']) AND $onlineExams['examQuestion'][$key]['type'] == "check"){
				$pass = true;
				if(isset($onlineExams['examQuestion'][$key]['Tans1'])){
					if(isset($value['answer1']) AND $value['answer1'] != true){
						$pass = false;
					}
				}
				if(isset($onlineExams['examQuestion'][$key]['Tans2'])){
					if(isset($value['answer2']) AND $value['answer2'] != true){
						$pass = false;
					}
				}
				if(isset($onlineExams['examQuestion'][$key]['Tans3'])){
					if(isset($value['answer3']) AND $value['answer3'] != true){
						$pass = false;
					}
				}
				if(isset($onlineExams['examQuestion'][$key]['Tans4'])){
					if(isset($value['answer4']) AND $value['answer4'] != true){
						$pass = false;
					}
				}
				if($pass == true){
					if(isset($onlineExams['examQuestion'][$key]['questionMark'])){
						$score += $onlineExams['examQuestion'][$key]['questionMark'];
					}else{
						$score++;
					}
				}
				unset($pass);
			}
			if(isset($onlineExams['examQuestion'][$key]['type']) AND $onlineExams['examQuestion'][$key]['type'] == "text"){
				$onlineExams['examQuestion'][$key]['ans1'] = explode(",",$onlineExams['examQuestion'][$key]['ans1']);
				if(in_array($value['answer'],$onlineExams['examQuestion'][$key]['ans1'])){
					if(isset($onlineExams['examQuestion'][$key]['questionMark'])){
						$score += $onlineExams['examQuestion'][$key]['questionMark'];
					}else{
						$score++;
					}
				}
			}
		}
		$onlineExamsGrades = onlineExamsGrades::where('examId',$id)->where('studentId',$this->data['users']->id)->first();
		$onlineExamsGrades->examId = Input::get('id') ;
		$onlineExamsGrades->studentId = $this->data['users']->id ;
		$onlineExamsGrades->examQuestionsAnswers = json_encode($answers) ;
		$onlineExamsGrades->examGrade = $score ;
		$onlineExamsGrades->examDate = time() ;
		$onlineExamsGrades->save();

		if($onlineExams['ExamShowGrade'] == 1){
			if($onlineExams['examDegreeSuccess'] != "0"){
				if($onlineExams['examDegreeSuccess'] <= $score){
					$score .= " - Succeeded";
				}else{
					$score .= " - Failed";
				}
			}
			$toReturn['grade'] = $score;
		}
		$toReturn['finish'] = true;
		return json_encode($toReturn);
	}

	function export($id,$type){
		if($this->data['users']->role != "admin") exit;
		if($type == "excel"){
			$classArray = array();
			$classes = classes::get();
			foreach ($classes as $class) {
				$classArray[$class->id] = $class->className;
			}

			$data = array(1 => array ('Student Roll','Full Name','Date took','Exam Grade'));
			$grades = \DB::table('onlineExamsGrades')
					->where('examId',$id)
					->leftJoin('users', 'users.id', '=', 'onlineExamsGrades.studentId')
					->select('onlineExamsGrades.id as id',
					'onlineExamsGrades.examGrade as examGrade',
					'onlineExamsGrades.examDate as examDate',
					'users.fullName as fullName',
					'users.id as studentId',
					'users.studentRollId as studentRollId')
					->get();
			foreach ($grades as $value) {
				$data[] = array ($value->studentRollId,$value->fullName,date("m/d/Y",$value->examDate) , $value->examGrade );
			}

			$xls = new Excel_XML('UTF-8', false, 'Exam grades Sheet');
			$xls->addArray($data);
			$xls->generateXML('Exam grades Sheet');
		}elseif ($type == "pdf") {
			$classArray = array();
			$classes = classes::get();
			foreach ($classes as $class) {
				$classArray[$class->id] = $class->className;
			}

			$header = array ('Student Roll','Full Name','Date took','Exam Grade');
			$data = array();
			$grades = \DB::table('onlineExamsGrades')
					->where('examId',$id)
					->leftJoin('users', 'users.id', '=', 'onlineExamsGrades.studentId')
					->select('onlineExamsGrades.id as id',
					'onlineExamsGrades.examGrade as examGrade',
					'onlineExamsGrades.examDate as examDate',
					'users.fullName as fullName',
					'users.id as studentId',
					'users.studentRollId as studentRollId')
					->get();
			foreach ($grades as $value) {
				$data[] = array ($value->studentRollId,$value->fullName,date("m/d/y",$value->examDate) , $value->examGrade );
			}

			$pdf = new FPDF();
			$pdf->SetFont('Arial','',10);
			$pdf->AddPage();
			// Header
			foreach($header as $col)
				$pdf->Cell(60,7,$col,1);
			$pdf->Ln();
			// Data
			foreach($data as $row)
			{
				foreach($row as $col)
					$pdf->Cell(60,6,$col,1);
				$pdf->Ln();
			}
			$pdf->Output();
		}
		exit;
	}
        
        function selectExams(){
            
            $learpath = new LearnPathController;
            $StudentLp =$learpath->averageCounting();
            $learpathList = $StudentLp["learnPaths"];
            $moduleids = array();
            foreach($learpathList as $lpthlist)
            {
                foreach($lpthlist['modules'] as $modules)
                {
                    $moduleids[]=$modules->mId;
                }
            }

            if(count($moduleids) <=0 )
            {
                return $this->panelInit->apiOutput(false,$this->panelInit->language['nodata'],$this->panelInit->language['nodataavailable']);
            }
            $moduleslist = \DB::table('modules')
            ->whereIn("id",$moduleids)
            ->get();


            $onExids = array();
            foreach($moduleslist as $modules)
            {
                foreach(json_decode($modules->onlineExamids) as $onlineExamids)
                {
                    $onExids[] =$onlineExamids;
                }
            }
			
            return $onExids;
        }
        
        public function onlineexamClasses($id)
	{
                $onlineexams = onlineExams::where('id',$id)->first()->toArray();
                $onexcls =json_decode($onlineexams["examClass"]);
                $toReturn["classes"] = classes::whereIn('id',$onexcls)->get()->toArray();
                $toReturn["onlineexamInfo"] = $onlineexams;
                return $toReturn;
        }
        public function viewSchedule($onid,$classid)
	{
                $students = \DB::table('users')->where("users.studentClass",$classid)->get();
                $assignmentSchedule = array();
                foreach($students as $stud){
                    $schedule= scheduleOnlineExams::where('userid',$stud->id)
                                  ->where("onid",$onid)
                                  ->first();
                    $onexamDeadLine = (count($schedule)>0)?$schedule->OnexDeadLine:"Not Set";
                    $onexamexamTime= (count($schedule)>0)?$schedule->examTime:"Not Set";
                    
                    $submitStatus= onlineexamsgrades::where('studentId',$stud->id)
                                  ->where("examId",$onid)
                                  ->first();
                    
                    $status = (count($submitStatus)>0)?'Exams Attended':"Exams Not Attended";
                    
                    $onlineExamSchedule[] = array(
                        "studentName" =>$stud->fullName,
                        "OnexDeadLine" =>$onexamDeadLine,
                        "examTime" =>$onexamexamTime,
                        "status" =>$status,
                    );
                }
                $toReturn["studentsSchedule"] = $onlineExamSchedule;
                $toReturn["classInfo"] = classes::where('id',$classid)->first()->toArray();
                $toReturn["onlineexamInfo"] = onlineExams::where('id',$onid)->first()->toArray();
                return $toReturn;
        }
}
