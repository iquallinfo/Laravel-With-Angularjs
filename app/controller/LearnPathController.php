<?php

class LearnPathController extends \BaseController {

	var $data = array();
	var $panelInit ;
	var $layout = 'dashboard';

	public function __construct(){
		$this->panelInit = new \DashboardInit();
		$this->data['panelInit'] = $this->panelInit;
		$this->data['breadcrumb']['Settings'] = \URL::to('/dashboard/languages');
		$this->data['users'] = \Auth::user();

		if(!$this->data['users']->hasThePerm('learnPath')){
			exit;
		}
	}
        public function listAll()
	{
		$toReturn = array();
		$toReturn['classes'] = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
                $allsubjects = subject::get()->toArray();
                $toReturn['allsubjects'] = $allsubjects;
		$classesArray = array();
		while (list(, $class) = each($toReturn['classes'])) {
			$classesArray[$class['id']] = array("classTitle"=>$class['className'],"subjects"=>json_decode($class['classSubjects']));
		}
               
                
		if($this->data['users']->role == "teacher"){
			$subjects = subject::where('teacherId',$this->data['users']->id)->get()->toArray();
		}
                else if($this->data['users']->role == "student"){
                     $studentClass = classes::where('id',$this->data['users']->studentClass)->first()->toArray();
                     $subids = json_decode($studentClass["classSubjects"]);
                     $subjects = subject::whereIn('id',$subids)->get()->toArray();
                }
                    
                else{
			$subjects = subject::get()->toArray();
		}
		$subjectArray = array();
		while (list(, $subject) = each($subjects)) {
			$subjectArray[$subject['id']] = $subject['subjectTitle'];
		}              
                $learnPath = new learnPath();

		if($this->data['users']->role == "student"){
                     /* checking grade level of student subject wise */
                        $toReturn = $this->averageCounting();
//                        echo "<pre>";
//                        print_r($toReturn);
//                        exit;
		}
                else   // If role is teacher or admin
                {
                
                    $learnPath = $learnPath->get();
                    $toReturn['learnPaths'] = array();
                    foreach ($learnPath as $key => $learnPath) {
                            $classnames = classes::whereIn('id',json_decode($learnPath->lpClasses))->select('className')->get()->toArray();
                            //$modules = Modules::whereIn('id',json_decode($learnPath->modules))->select('moduleTitle')->get()->toArray();
                            $subject = subject::where('id',$learnPath->subject)->select('subjectTitle')->first()->toArray();
                            $toReturn['learnPaths'][$key]['id'] = $learnPath->id;
                            $toReturn['learnPaths'][$key]['lpTitle'] = $learnPath->lpTitle;
                            $toReturn['learnPaths'][$key]['lpClasses'] = $classnames;
                            $toReturn['learnPaths'][$key]['subject'] = $subject;
                            $toReturn['learnPaths'][$key]['status'] = ($learnPath->status == 1)?"On":"Off";
                            $toReturn['learnPaths'][$key]['is_default'] = $learnPath->is_default;
                            $yearLevel ="";
                            $ylevel= yearlevel::where('id',$learnPath->yearlevel)->get()->toArray();
                            if(count($ylevel) > 0){
                                  $yearLevel = $ylevel[0]["title"];
                               }
                            $toReturn['learnPaths'][$key]['yearlevel'] =$yearLevel;
                            $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                            $toReturn['learnPaths'][$key]['gradelevel'] =$levelName["gradeName"];

                    }
                }
                
                $modules = new Modules();
		$modules = $modules->get();
                $toReturn['modules'] = array();
		foreach ($modules as $key => $modules) {
			$toReturn['modules'][$key]['id'] = $modules->id;
			$toReturn['modules'][$key]['moduleTitle'] = $modules->moduleTitle;
			$toReturn['modules'][$key]['requirements'] = $modules->requirements;
                        if($modules->endTime !="")
                        {
                            $toReturn['modules'][$key]['endTime'] = date("F j, Y",$modules->endTime);
                        }
		}
                //$gradelevel = newgradelevels();

		$gradelevels =gradelevels::get()->toArray();
                $toReturn['gradelevels'] = $gradelevels;
                
                $yearlevels = yearlevel::get()->toArray();
                $toReturn['yearlevels'] = $yearlevels;
                
		$toReturn['userRole'] = $this->data['users']->role;
		return $toReturn;
	}

	public function delete($id){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent")
                    exit;
		if ( $postDelete = learnPath::where('id', $id)->first() )
                {
                        $postDelete->delete();
                    return $this->panelInit->apiOutput(true,$this->panelInit->language['learnpath'],$this->panelInit->language['learnpathdeletedsuccessfully']);
                }else{
            return $this->panelInit->apiOutput(false,$this->panelInit->language['learnpath'],$this->panelInit->language['lpnoexists']);
        }
	}

	public function create(){
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		$learnPath = new learnPath();
		$learnPath->lpTitle = Input::get('lpTitle');
		$learnPath->lpClasses = json_encode(Input::get('lpClasses'));
                $learnPath->gradelevel = Input::get('gradelevel');
                $learnPath->yearlevel = Input::get('yearlevel');
                $learnPath->modules = json_encode(Input::get('moduleList'));
                $moduleids = [];
                $moduleList = Input::get('moduleList');
                foreach($moduleList as $ml)
                {
                    $moduleids[] = $ml['mId'];
                }
                $learnPath->moduleids = json_encode($moduleids);
                $learnPath->subject = Input::get('subject');
//                $learnPath->endTime = strtotime(Input::get('endTime'));
                $learnPath->status = 1;
		$learnPath->added_by = $this->data['users']->role;
                $learnPath->added_name = $this->data['users']->id;
                
                $moduleids = Input::get('moduleid');
                $subject = Input::get('subject');
                $sortorder = Input::get('sortorder');
		$learnPath->save();
                
                $subject = subject::where('id',$learnPath->subject)->select('subjectTitle')->first()->toArray();
                $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                $learnPath->subject = $subject;
                $learnPath->gradelevel = $levelName["gradeName"];
                $learnPath->endTime = date("F j, Y",$learnPath->endTime);
                $learnPath->status = ($learnPath->status == 1)?"On":"Off";
                $yearLevel ="";      
                $ylevel= yearlevel::where('id',$learnPath->yearlevel)->get()->toArray();
                    if(count($ylevel) > 0){
                        $yearLevel = $ylevel[0]["title"];
                    }
                $learnPath->yearlevel = $yearLevel;
                
                return $this->panelInit->apiOutput(true,$this->panelInit->language['learnpath'],$this->panelInit->language['lpadded'],$learnPath->toArray());
	}

	function fetch($id){
		/* Check module if deleted and edit it */
                $learnPath = learnPath::find($id);
                $moduleslist = \DB::table('modules')->get();
                
                $modulesids =\DB::table('modules')->select('id')->get();
                $mids = array();
                foreach($modulesids as $ids)
                {
                    $mids[] = $ids->id;
                }
                $cur_modules = json_decode($learnPath->modules);
                $newdata = array();
                foreach($cur_modules as $cmd)
                {
                    if(in_array($cmd->mId,$mids))
                    {
                        foreach($moduleslist as $mdlist)
                        {                  
                            if($mdlist->id == $cmd->mId)
                            {
                                $newdata[] = array(
                                    "id" => $mdlist->id,
                                    "mTitle" => $mdlist->moduleTitle,
                                );
                                break;
                            }   
                        }
                    }
                }
                $finalMdData = array();
                foreach($cur_modules as $cdata)
                {
                     foreach($newdata as $ndata)
                     {
                         if($cdata->mId == $ndata['id'])
                         {
                            $finalMdData[]=array(
                                "mId"=>$ndata['id'],
//                                "mstatus"=>$cdata->mstatus,
                                "morder"=>$cdata->morder,
                                "mtitle"=>$ndata['mTitle'],
                             );
                         }
                     }
                            
                }
                  $learnPath->modules = json_encode($finalMdData);
                  $learnPath->save();
                
                /* Check module if deleted and edit it end */
                
                
                $learnPaths= learnPath::where('id',$id)->first()->toArray();
		$learnPaths['lpClasses'] = json_decode($learnPaths['lpClasses']);
                
                $modulelists = json_decode($learnPaths['modules']);
                
                for($i=0; $i < count($modulelists); $i++){
                    for($j=0; $j < count($modulelists); $j++){
                        if($modulelists[$j]->morder > $modulelists[$i]->morder){
                            $temp = $modulelists[$i];
                            $modulelists[$i] =  $modulelists[$j];
                            $modulelists[$j]= $temp;
                        }
                    }
                }
                
                $learnPaths['moduleList'] = $modulelists;
                $learnPaths['moduleids'] = $modulelists;
                
                
//                $learnPaths['endTime'] = date("m/d/Y",$learnPaths['endTime']);
                
                $gradelevels = \DB::table('classgradelevels')
                                ->leftJoin('gradelevels', 'gradelevels.id', '=', 'classgradelevels.level')
                                ->select('gradelevels.id as gid',
                                        'classgradelevels.*',
                                        'classgradelevels.id as cgid',
                                        'gradelevels.gradeName as gradeName')
                                ->whereIn("classgradelevels.class",$learnPaths['lpClasses'])
                                ->groupBy('gradelevels.id')
                                ->get();
//                $gradelevels =gradelevels::get()->toArray();
                $learnPaths['gradelevels'] = $gradelevels;
                $DashboardController = new DashboardController();
		$learnPaths['subjectsname'] = $DashboardController->subjectList($learnPaths['lpClasses']);
                
                $classList = classes::where('classAcademicYear',$this->panelInit->selectAcYear)->get()->toArray();
                $selClasses = $learnPaths['lpClasses'];
                $finalclass = array();
                
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
                $moddata = array(
                    "mdClasses" => json_decode($learnPath->lpClasses),
                    "subject" => $learnPath->subject,
                    "gradelevel" => $learnPath->gradelevel,
                    "yearlevel" => $learnPath->yearlevel,
                );
                $learnPaths['modules'] = $this->selectModules($moddata);
                
                
                $learnPaths["classes"] = $finalclass;
		return $learnPaths;
	}
        
        function addModule($id){
		$learnPaths= learnPath::where('id',$id)->first()->toArray();
		$learnPaths['lpClasses'] = json_decode($learnPaths['lpClasses']);
		return $learnPaths;
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
                
                $learnPath = learnPath::find($id);
		$learnPath->lpTitle = Input::get('lpTitle');
		$learnPath->lpClasses = json_encode(Input::get('lpClasses'));
                $learnPath->gradelevel = Input::get('gradelevel');
                $learnPath->yearlevel = Input::get('yearlevel');
                //$learnPath->modules = json_encode(Input::get('modules'));
                $learnPath->modules = json_encode(Input::get('moduleList'));
                $moduleids = [];
                $moduleList = Input::get('moduleList');
                foreach($moduleList as $ml)
                {
                    $moduleids[] = $ml['mId'];
                }
                $learnPath->moduleids = json_encode($moduleids);
                $learnPath->subject = Input::get('subject');
                $learnPath->endTime = strtotime(Input::get('endTime'));
                $learnPath->status = Input::get('status');

		$learnPath->added_by = $this->data['users']->role;
                $learnPath->added_name = $this->data['users']->id;
		$learnPath->save();
                
                $subject = subject::where('id',$learnPath->subject)->select('subjectTitle')->first()->toArray();
                if(Input::get('levelsetfor') == 1)
                {
                $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                $learnPath->gradelevel = $levelName;
                }
                $learnPath->subject = $subject;
                
                $learnPath->endTime = date("F j, Y",$learnPath->endTime);
                $learnPath->status = ($learnPath->status == 1)?"On":"Off";
                $yearLevel ="";
                $ylevel= yearlevel::where('id',$learnPath->yearlevel)->get()->toArray();
                if(count($ylevel) > 0){
                      $yearLevel = $ylevel[0]["title"];
                }
                $learnPath->yearlevel = $yearLevel;
                $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                $learnPath->gradelevel = $levelName["gradeName"];
                                            
                return $this->panelInit->apiOutput(true,$this->panelInit->language['learnpath'],$this->panelInit->language['lpedited'],$learnPath->toArray());
		
	}
        function viewLearnPath($id){
		/* Check module if deleted and edit it */
                $learnPath = learnPath::find($id);
                $moduleslist = \DB::table('modules')->get();
                
                $modulesids =\DB::table('modules')->select('id')->get();
                $mids = array();
                foreach($modulesids as $ids)
                {
                    $mids[] = $ids->id;
                }
                $cur_modules = json_decode($learnPath->modules);
                $newdata = array();
                foreach($cur_modules as $cmd)
                {
                    if(in_array($cmd->mId,$mids))
                    {
                        foreach($moduleslist as $mdlist)
                        {                  
                            if($mdlist->id == $cmd->mId)
                            {
                                $newdata[] = array(
                                    "id" => $mdlist->id,
                                    "mTitle" => $mdlist->moduleTitle,
                                );
                                break;
                            }   
                        }
                    }
                }
                $finalMdData = array();
                foreach($cur_modules as $cdata)
                {
                     foreach($newdata as $ndata)
                     {
                         if($cdata->mId == $ndata['id'])
                         {
                            $finalMdData[]=array(
                                "mId"=>$ndata['id'],
//                                "mstatus"=>$cdata->mstatus,
                                "morder"=>$cdata->morder,
                                "mtitle"=>$ndata['mTitle'],
                             );
                         }
                     }
                            
                }
                  $learnPath->modules = json_encode($finalMdData);
                  $learnPath->save();
                
                /* Check module if deleted and edit it end */
				
		$learnPaths= learnPath::where('id',$id)->first()->toArray();
                $subject = subject::where('id',$learnPaths['subject'])->select('subjectTitle')->first()->toArray();
                if($learnPaths['gradelevel'] !=0){
                    $levelName =gradelevels::where('id',$learnPaths['gradelevel'])->select('gradeName')->first()->toArray();
                }
                else{
                    $levelName["gradeName"]="Default";
                }
                $learnPaths['subject'] = $subject;
                $learnPaths['gradelevel'] = $levelName;
		$learnPaths['lpClasses'] = json_decode($learnPaths['lpClasses']);
                
                $learnPaths['moduleids'] = json_decode($learnPaths['moduleids']);
                //$learnPaths['moduleList'] = json_decode($learnPaths['modules']);
//                $learnPaths['endTime'] = date("F j, Y",$learnPaths['endTime']);
                $DashboardController = new DashboardController();
		$learnPaths['subjectsname'] = $DashboardController->subjectList($learnPaths['lpClasses']);
                
				$moduleset  = array();
			
				
				if(count($learnPaths['moduleids']) > 0) 
				{
					$moduleset = modules::whereIn('id',$learnPaths['moduleids'])->get()->toArray();
				}
                
					$studentDetails =user::where('id',$this->data['users']->id)->first()->toArray();
					
					//$moduleids = json_decode($learnPaths['moduleids']);
					$moduleList = json_decode($learnPaths['modules']);
					$modList="";
					for($i=0;$i<count($moduleset);$i++)
					{
						$cls = json_decode($moduleset[$i]["mdClasses"]);
						
						if($cls !="")
						{
                                                    if (in_array($studentDetails["studentClass"], $cls)) {
                                                         $modList[] =$moduleList[$i];
                                                    }
						}
					}
					
					$learnPaths['moduleList'] = $modList;
				
                $assignmentsList = [];
                $onlineExamList = [];
                $fmid="";
                
                foreach($moduleset as $modset)
                {
                  $mdClasses = json_decode($modset["mdClasses"]);
                  if($mdClasses !="")
                  {
                    if (in_array($studentDetails["studentClass"], $mdClasses)) {
                        $fmid[] = $modset["id"];
                   
                    if(count(json_decode($modset["assignmentids"])) != 0)
                    {
                      $assignmentsList[$modset["id"]] = assignments::whereIn('id',json_decode($modset["assignmentids"]))->get()->toArray();
                      $modassignments =  json_decode($modset["assignments"]);
                      if(count($modassignments) > 0)
                      {
                        for($i=0; $i < count($assignmentsList[$modset["id"]]);$i++)               
                        {
                          $assignmentsList[$modset["id"]][$i]['assorder'] = $modassignments[$i]->assorder; 
                          $assignmentsList[$modset["id"]][$i]['assId'] = $modassignments[$i]->assId; 
                        }
                      }
                      
                    }
                    else
                    {
                        $assignmentsList[$modset["id"]] = "";
                    }
                    
                    
                    
                    if(count(json_decode($modset["onlineExamids"])))
                    {
                      $onlineExamList[$modset["id"]] = onlineexams::whereIn('id',json_decode($modset["onlineExamids"]))->get()->toArray(); 
                      
                      
                       $modonlineexams =  json_decode($modset["onlineExams"]);
                       if(count($modonlineexams) > 0 && !empty($modonlineexams)) 
                       {
                            for($i=0; $i < count($onlineExamList[$modset["id"]]);$i++)               
                            {
                                $onlineExamList[$modset["id"]][$i]["oexorder"] = $modonlineexams[$i]->oexorder;
                                $onlineExamList[$modset["id"]][$i]["oexId"] = $modonlineexams[$i]->oexId;
                            }
                       }
                       
                    }
                    else
                    {
                        $onlineExamList[$modset["id"]] = "";
                    }
                     }
                  }
                }
                $moduleList = $fmid;
                
                if(count($moduleset) > 0)
                {
                    for($i=0; $i < count($moduleList);$i++)               
                    {
                        $modulesetlist = array();
                        $learnPaths['moduleList'][$i]->assignmentsList = $assignmentsList[$learnPaths['moduleList'][$i]->mId];
                        $learnPaths['moduleList'][$i]->onlineExamList =  $onlineExamList[$learnPaths['moduleList'][$i]->mId];
                        //$learnPaths['moduleList'][$i]->modulesetlist = "";
                        if(count($learnPaths['moduleList'][$i]->assignmentsList) > 0 && !empty($learnPaths['moduleList'][$i]->assignmentsList))
                        {
                            foreach($learnPaths['moduleList'][$i]->assignmentsList as $asslist)
                            {
                                $modulesetlist[] = $asslist;
                            }
                            //$learnPaths['moduleList'][$i]->modulesetlist = $modulesetlist;
                        }
                        if(count($learnPaths['moduleList'][$i]->onlineExamList) > 0 && !empty($learnPaths['moduleList'][$i]->onlineExamList))
                        {
                            foreach($learnPaths['moduleList'][$i]->onlineExamList as $onexlist)
                            {
                                $modulesetlist[] = $onexlist;
                            }
                            //$learnPaths['moduleList'][$i]->modulesetlist = $modulesetlist;
                        }
                        
                        
                        
                        for($mi=0;$mi <count($modulesetlist);$mi++)
                        {

                            if(isset($modulesetlist[$mi]['assId'])) 
                            {
                                $modulesetlist[$mi]['mixid'] = $modulesetlist[$mi]['assId'];
                                $modulesetlist[$mi]['sortorder'] = $modulesetlist[$mi]['assorder'];
                                $modulesetlist[$mi]['setid'] = "assid";
                                $check = $this->viewGradeFeedback($modulesetlist[$mi]['assId']);
                                $modulesetlist[$mi]['gradefeedback'] = (count($check["assignments"]) > 0 )?"1":"0";
                            }
                            if(isset($modulesetlist[$mi]['oexId'])) 
                            {
                                $modulesetlist[$mi]['mixid'] = $modulesetlist[$mi]['oexId'];
                                $modulesetlist[$mi]['sortorder'] = $modulesetlist[$mi]['oexorder'];
                                $modulesetlist[$mi]['setid'] = "oexid";
                                $modulesetlist[$mi]['gradefeedback'] ="0";
                            }
                        
                        }
                        for($mi=0; $mi < count($modulesetlist); $mi++){
                            for($mj=0; $mj < count($modulesetlist); $mj++){
                                if($modulesetlist[$mj]['sortorder'] > $modulesetlist[$mi]['sortorder']){
                                    $temp = $modulesetlist[$mi];
                                    $modulesetlist[$mi] =  $modulesetlist[$mj];
                                    $modulesetlist[$mj]= $temp;
                                }
                            }
                        }
                        $learnPaths['moduleList'][$i]->modulesetlist = $modulesetlist;
                    }
                  
                }
                
                
                $learnPaths["assignmentsList"] = $assignmentsList;
                $learnPaths["onlineExamList"] = $onlineExamList;
                
                
             
                
                for($i=0; $i < count($learnPaths['moduleList']); $i++){
                    for($j=0; $j < count($learnPaths['moduleList']); $j++){
                        if($learnPaths['moduleList'][$j]->morder > $learnPaths['moduleList'][$i]->morder){
                            $temp = $learnPaths['moduleList'][$i];
                            $learnPaths['moduleList'][$i] =  $learnPaths['moduleList'][$j];
                            $learnPaths['moduleList'][$j]= $temp;
                        }
                    }
                }
                
//                for($i=0; $i < count($learnPaths['moduleList']); $i++){
//                    echo "<pre>";
//                    print_r($learnPaths['moduleList'][$i]);
//                    echo "</pre>";
//                }
//                
               
		return $learnPaths;
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
        
        function averageCounting(){
            
            /* checking grade level of student subject wise */
                                
                                $id = \Auth::user()->id;
                                $studentClass = classes::where('id',$this->data['users']->studentClass)->first()->toArray();
                                $subids = json_decode($studentClass["classSubjects"]);
                                //$subjects = subject::whereIn('id',$subids)->get()->toArray();
                                $userDetail = user::where('id',$id)->first()->toArray();
                                $marks = array();
                                $examIds = array();
                                $examsList = examsList::where('examAcYear',$this->panelInit->selectAcYear)->get();
                                foreach ($examsList as $exam) {
                                        $marks[$exam->id] = array("title"=>$exam->examTitle,"examId"=>$exam->id,"studentId"=>$id);
                                        $examIds[] = $exam->id;
                                }
                                
                                $subject = subject::whereIn('id',$subids)->get();
//                                echo "<pre>";
//                                print_r($subject);
//                                echo "</pre>";
//                                exit;
                                //$subject = subject::whereIn("id",$subids)->get();
                                foreach ($subject as $sub) {
                                        $subjectArray[$sub->id] = array('subjectTitle'=>$sub->subjectTitle,'passGrade'=>$sub->passGrade,'finalGrade'=>$sub->finalGrade);
                                }
                                
                               
                                /* Grade levels */
                                //$gradeLevels = gradeLevels::get();

                                $gradeLevels = \DB::table('classgradelevels')
					->leftJoin('gradelevels', 'gradelevels.id', '=', 'classgradelevels.level')
					->select('classgradelevels.*',
                                        'classgradelevels.id as cgid',
					'gradelevels.id as gid',
                                        'gradelevels.gradePoints as points',
					'gradelevels.gradeName as gradeName',
					'classgradelevels.gradefrom as gradeFrom',
					'classgradelevels.gradeto as gradeTo')
					->where('class',$userDetail['studentClass'])
					->get();

//                                echo "<pre>";
//                                print_r($gradeLevels);
//                                echo "</pre>";
//                                exit;
                                
                                $gradeLevelsArray = array();
                                foreach ($gradeLevels as $grade) {
                                        $gradeLevelsArray[$grade->gradeName] =
                                                array('from'=>$grade->gradeFrom,"to"=>$grade->gradeTo,'points'=>$grade->points,'is_default'=>$grade->is_default);
                                }
				$subjectset = array();
                                
                                       
                                /* =========== Exam Marks ============= */
                                $examMarks = examMarks::where('studentId',$id)->whereIn('examId',$examIds)->get();
                                if(count($examMarks) != 0){
//                                        return $this->panelInit->apiOutput(false,$this->panelInit->language['Marksheet'],$this->panelInit->language['studentHaveNoMarks']);
//                                        exit;
//                                }
                                foreach ($examMarks as $mark) {
                                        if(!isset($marks[$mark->examId]['counter'])){
                                                $marks[$mark->examId]['counter'] = 0;
                                                $marks[$mark->examId]['points'] = 0;
                                                $marks[$mark->examId]['totalMarks'] = 0;
                                        }
                                        $marks[$mark->examId]['counter'] ++;
                                        $marks[$mark->examId]['data'][$mark->id]['subjectName'] = $subjectArray[$mark->subjectId]['subjectTitle'];
                                        $marks[$mark->examId]['data'][$mark->id]['subjectId'] = $mark->subjectId;
                                        $marks[$mark->examId]['data'][$mark->id]['examMark'] = $mark->examMark;
                                        $marks[$mark->examId]['data'][$mark->id]['attendanceMark'] = $mark->attendanceMark;
                                        $marks[$mark->examId]['data'][$mark->id]['markComments'] = $mark->markComments;
                                        $marks[$mark->examId]['data'][$mark->id]['passGrade'] = $subjectArray[$mark->subjectId]['passGrade'];
                                        $marks[$mark->examId]['data'][$mark->id]['finalGrade'] = $subjectArray[$mark->subjectId]['finalGrade'];
                                        if($marks[$mark->examId]['data'][$mark->id]['passGrade'] != ""){
                                                if($marks[$mark->examId]['data'][$mark->id]['examMark'] >= $marks[$mark->examId]['data'][$mark->id]['passGrade']){
                                                        $marks[$mark->examId]['data'][$mark->id]['examState'] = "Pass";
                                                }else{
                                                        $marks[$mark->examId]['data'][$mark->id]['examState'] = "Failed";
                                                }
                                        }

                                        reset($gradeLevelsArray);
                                        while (list($key, $value) = each($gradeLevelsArray)) {
                                                if($mark->examMark >= $value['from'] AND $mark->examMark <= $value['to']){
                                                        $marks[$mark->examId]['points'] += $value['points'];
                                                        $marks[$mark->examId]['data'][$mark->id]['grade'] = $key;
                                                        $marks[$mark->examId]['totalMarks'] += $mark->examMark;
                                                        if($this->data['panelInit']->settingsArray['attendanceOnMarksheet'] == 1){
                                                                $marks[$mark->examId]['totalMarks'] += $mark->attendanceMark;
                                                        }
                                                        break;
                                                }
                                        }
                                }

                                while (list($key, $value) = each($marks)) {
                                        if(isset($value['points']) AND $value['counter']){
                                                $marks[$key]['pointsAvg'] = $value['points'] / $value['counter'];
                                        }
                                }
//                                echo "<pre>";
//                                print_r($marks);
//                                echo "</pre>";
                                
                                foreach($marks as $key => $mrData)
                                {
                                    foreach($mrData["data"] as $subData)
                                    {
                                        if(!isset($subjectset[$subData["subjectId"]]))
                                        {
                                            
                                            $subjectset[$subData["subjectId"]]= array(
                                                "counter" => 1,
                                                "subjectName" => $subData["subjectName"],
                                                "totalMarks" => $subData["examMark"],
                                                "subjectAverage" =>$subData["examMark"],
                                            ); 
                                        }
                                        else
                                        {
                                             $subjectset[$subData["subjectId"]] = array(
                                                "counter" => $subjectset[$subData["subjectId"]]["counter"] +=1,  
                                                "subjectName" => $subData["subjectName"],
                                                "totalMarks" => $subjectset[$subData["subjectId"]]["totalMarks"] +  $subData["examMark"],
                                                "subjectAverage" => ($subjectset[$subData["subjectId"]]["totalMarks"] + $subData["examMark"])/($subjectset[$subData["subjectId"]]["counter"]),     
                                             ); 
                                        }
                                        
//                                        reset($gradeLevelsArray);
//                                        while (list($key, $value) = each($gradeLevelsArray)) {
//                                                if($subjectset[$subData["subjectId"]]["subjectAverage"] >= $value['from'] AND $subjectset[$subData["subjectId"]]["subjectAverage"] <= $value['to']){
//                                                        $subjectset[$subData["subjectId"]]['grade'] = $key;
//                                                        break;
//                                                }
//                                        }
                                        
                                    }
                                }
                                }
                              $assignmentset = array();
                              $asgnData = \DB::table('assignmentsanswers')
				->where('userId',$this->data['users']->id)
                                ->leftJoin('assignments', 'assignments.id', '=', 'assignmentsanswers.assignmentId')
                                ->select('assignmentsanswers.*','assignments.id as id',
                                'assignmentsanswers.id as ansId',
                                'assignments.subjectId as subjectId')
                                ->get();  
                                foreach($asgnData as $asd)
                                {
                                    if(!isset($assignmentset[$asd->subjectId]))
                                    {
                                        $assignmentset[$asd->subjectId] = array(
                                            "counter" => 1,
                                            "subjectId" => $asd->subjectId,
                                            "assignmentmarks" => $asd->gradepoint,
                                            "assignmentAverage" => $asd->gradepoint,
                                        );
                                    }
                                    else
                                    {
                                        $assignmentset[$asd->subjectId] = array(
                                            "counter" => $assignmentset[$asd->subjectId]["counter"] +=1,
                                            "subjectId" => $asd->subjectId,
                                            "assignmentmarks" => $assignmentset[$asd->subjectId]["assignmentmarks"] + $asd->gradepoint,
                                            "assignmentAverage" => ($assignmentset[$asd->subjectId]["assignmentmarks"] + $asd->gradepoint)/ $assignmentset[$asd->subjectId]["counter"],
                                        );
                                    }
                                }

                               $onlineExamset = array();
                               $onexData = \DB::table('onlineexamsgrades')
				->where('studentId',$this->data['users']->id)
                                ->leftJoin('onlineexams', 'onlineexams.id', '=', 'onlineexamsgrades.examId')
                                ->select('onlineexamsgrades.*','onlineexams.id as id',
                                'onlineexamsgrades.id as onlgrId',
                                'onlineexams.examSubject as subjectId')
                                ->get();  
                                foreach($onexData as $onex)
                                {
                                    if(!isset($onlineExamset[$onex->subjectId]))
                                    {
                                        $onlineExamset[$onex->subjectId] = array(
                                            "counter" => 1,
                                            "subjectId" => $onex->subjectId,
                                            "onlineExamMarks" => $onex->examGrade,
                                            "onlineExamAverage" => $onex->examGrade,
                                        );
                                    }
                                    else
                                    {
                                        $onlineExamset[$onex->subjectId] = array(
                                            "counter" => $onlineExamset[$onex->subjectId]["counter"] +=1,
                                            "subjectId" => $onex->subjectId,
                                            "onlineExamMarks" => $onlineExamset[$onex->subjectId]["onlineExamMarks"] + $onex->examGrade,
                                            "onlineExamAverage" => ($onlineExamset[$onex->subjectId]["onlineExamMarks"] + $onex->examGrade)/ $onlineExamset[$onex->subjectId]["counter"],
                                        );
                                    }
                                }
                                $overallMarks = array();
                                $zero_average = 0;
                                $totalsubjecs = count($subject);
                                foreach($subject as $sub)
                                {
                                    $counter=0;
                                    $overallAvg = 0;
//                                    echo "<pre>";
//                                    print_r($sub["id"]);
//                                    echo "</pre>";
									
                                    if(!isset($overallMarks[$sub["id"]]))
                                    {
                                        if(isset($subjectset[$sub["id"]]))
                                        {
                                            $counter++;
											//echo $counter." subject <Br>";
                                            $overallAvg += $subjectset[$sub["id"]]["subjectAverage"];
                                        }
                                        if(isset($assignmentset[$sub["id"]]))
                                        {
                                            $counter++;
											//echo $counter." assgn <Br>";
                                            $overallAvg += $assignmentset[$sub["id"]]["assignmentAverage"];
                                        }
                                        if(isset($onlineExamset[$sub["id"]]))
                                        {
                                            $counter++;
											//echo $counter." exam <Br>";
                                            $overallAvg += $onlineExamset[$sub["id"]]["onlineExamAverage"];
                                        }
										//echo $counter." final <Br>";
                                        if($counter != 0)
                                        {
                                            $avgMarks = $overallAvg/$counter;
                                        }
                                        else
                                        {
                                            $avgMarks = 0;
                                            $zero_average++;
                                        }
                                        
                                        $overallMarks[$sub["id"]] = array(
                                           "subjectName" => $sub["subjectTitle"],
                                           "assignmentAverage" => (isset($assignmentset[$sub["id"]]["assignmentAverage"]))? $assignmentset[$sub["id"]]["assignmentAverage"]:"",
                                           "onlineExamAverage" => (isset($onlineExamset[$sub["id"]]["onlineExamAverage"]))? $onlineExamset[$sub["id"]]["onlineExamAverage"]:"",
                                           "subjectAverage" =>(isset($subjectset[$sub["id"]]["subjectAverage"]))? $subjectset[$sub["id"]]["subjectAverage"]:"",
                                           "Counter"       => $counter,
                                           "Average"     => round($avgMarks),
                                        );
                                        reset($gradeLevelsArray);
                                        
                                        
                                        while (list($key, $value) = each($gradeLevelsArray)) {
                                            if($overallMarks[$sub["id"]]["Average"] <=0)
                                            {
                                                if($value["is_default"] == 1)
                                                {
                                                    $overallMarks[$sub["id"]]['grade'] = $key;
                                                }
                                            }
                                            else
                                            {
                                                if($overallMarks[$sub["id"]]["Average"] >= $value['from'] AND $overallMarks[$sub["id"]]["Average"] <= $value['to']){
                                                    $overallMarks[$sub["id"]]['grade'] = $key;
                                                    break;
                                                }
                                                else
                                                {
                                                        $overallMarks[$sub["id"]]['grade'] = "";
                                                }
                                            }
                                        }
                                
                                    }
                                }
                                /* checking grade level of student subject wise end */
                                
//                                echo "<pre>";
//                                print_r($overallMarks);
//                                echo "</pre>";
//                                echo $zero_average;
//                                exit;
                                
                                
                                $learnPath = new learnPath();
                                
                                if($this->data['users']->role == "student"){
                                    $learnPath = $learnPath->where("status","1")->get();
                                }
                                else{
                                    $learnPath = $learnPath->get();
                                }
                                $toReturn['learnPaths'] = array();
                                $sub_once_enter=[];
                                
                                foreach ($learnPath as $key => $learnPath) {
                                        $classnames = classes::whereIn('id',json_decode($learnPath->lpClasses))->select('className')->get()->toArray();
                                        //$modules = Modules::whereIn('id',json_decode($learnPath->modules))->select('moduleTitle')->get()->toArray();
                                        $subject = subject::where('id',$learnPath->subject)->select('id','subjectTitle')->first()->toArray();
                                        
                                        $gradeLevels = \DB::table('overrulegrade')
                                                ->where('studentid',$id)
                                                ->where('subject',$subject["id"])
                                                ->get();
                                        $default_level=[];
                                        $df_subject = [];
                                        $is_default =0;
                                        foreach($gradeLevels as $glevel)
                                        {                                        
                                            $default_level[]=$glevel->level;
                                            $df_subject[]=$glevel->subject;
                                        }
                                        if(count($default_level) > 0)
                                        {
                                            $is_default = in_array($learnPath->subject,$df_subject);   
                                        }

                                        $lpid ="";
                                        if(count($default_level)> 0)
                                        {
                                            $lpid = $default_level[0];
                                        }
                                        else
                                        {
//                                            echo $learnPath->lpTitle."\n";
//                                            echo $learnPath->gradelevel."\n";
                                            $lpid = $learnPath->gradelevel;
                                        }
                                        
                                        $levelName =gradelevels::where('id',$lpid)->select('gradeName')->first()->toArray();
        //                                if($learnPath->gradelevel != 0)
        //                                {
        //                                    $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
        //                                }

                                        $studentDetails =user::where('id',$id)->first()->toArray();

                                        $levelClass = json_decode($learnPath->lpClasses);

                                        if (in_array($studentDetails["studentClass"], $levelClass)) {
                                            if (in_array($learnPath->subject, $subids)) {
//                                                echo $is_default."is default \n";
                                            if($is_default !=1){
//                                                echo $levelName["gradeName"]."\n";
                                                if($overallMarks[$learnPath->subject]["grade"] == $levelName["gradeName"]){
                                                    $toReturn['learnPaths'][$key]['id'] = $learnPath->id;
                                                    $toReturn['learnPaths'][$key]['lpTitle'] = $learnPath->lpTitle;
                                                    $toReturn['learnPaths'][$key]['lpClasses'] = $classnames;
                                                    $toReturn['learnPaths'][$key]['modules'] = json_decode($learnPath->modules);
                                                    $toReturn['learnPaths'][$key]['subject'] = $subject;
        //                                            $toReturn['learnPaths'][$key]['endTime'] = date("F j, Y",$learnPath->endTime);
                                                    $toReturn['learnPaths'][$key]['status'] = ($learnPath->status == 1)?"On":"Off";
                                                    $toReturn['learnPaths'][$key]['is_default'] = $learnPath->is_default;
                                                    $yearLevel ="";
                                                    $ylevel= yearlevel::where('id',$learnPath->yearlevel)->get()->toArray();
                                                    if(count($ylevel) > 0){
                                                          $yearLevel = $ylevel[0]["title"];
                                                       }
                                                    $toReturn['learnPaths'][$key]['yearlevel'] =$yearLevel;
                                                    $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                                                    $toReturn['learnPaths'][$key]['gradelevel'] =$levelName["gradeName"];
                                                }
                                            }
                                            if($is_default == 1){ 
                                                if(!in_array($subject,$sub_once_enter))
                                                {    
                                                    $sub_once_enter[]=$subject;
    
                                                    $toReturn['learnPaths'][$key]['id'] = $learnPath->id;
                                                    $toReturn['learnPaths'][$key]['lpTitle'] = $learnPath->lpTitle;
                                                    $toReturn['learnPaths'][$key]['lpClasses'] = $classnames;
       
                                                     $toReturn['learnPaths'][$key]['modules'] = json_decode($learnPath->modules);
                                                    $toReturn['learnPaths'][$key]['subject'] = $subject;
        //                                            $toReturn['learnPaths'][$key]['endTime'] = date("F j, Y",$learnPath->endTime);
                                                    $toReturn['learnPaths'][$key]['status'] = ($learnPath->status == 1)?"On":"Off";
                                                    $toReturn['learnPaths'][$key]['is_default'] = $learnPath->is_default; 
                                                    $yearLevel ="";
                                                    $ylevel= yearlevel::where('id',$learnPath->yearlevel)->get()->toArray();
                                                    if(count($ylevel) > 0){
                                                          $yearLevel = $ylevel[0]["title"];
                                                       }
                                                    $toReturn['learnPaths'][$key]['yearlevel'] =$yearLevel;
                                                    $levelName =gradelevels::where('id',$learnPath->gradelevel)->select('gradeName')->first()->toArray();
                                                    $toReturn['learnPaths'][$key]['gradelevel'] =$levelName["gradeName"];
                                                }

                                            }
                                        }
                                        }
                                }
            
            return $toReturn;
        }
        
        public function selectModules($moddata = array()){
                
                $subject =$moddata['subject'];
                $yearlevel =$moddata['yearlevel'];
                $gradelevel =$moddata['gradelevel'];
                $toReturn = array();
		$selectedClass = "";
                $mdcids = array();
            if(count($moddata['mdClasses']) !=0)
            {
                if(count($moddata['mdClasses']) !=0)
                {
                    $selectedClass =$moddata['mdClasses'];
                }
                else
                {
                    $selectedClass ="";
                }
                
                if(is_array($selectedClass)){    
                    $modulesData = DB::table('modules')->whereNotNull('mdClasses')->get();
                    for($i =0; $i < count($selectedClass);$i++)
                    {
                        foreach($modulesData as $mdc)
                        {
                            
                            if(count(json_decode($mdc->mdClasses)) != 0 )
                            {
                                if(in_array($selectedClass[$i],json_decode($mdc->mdClasses)))
                                {
                                    if(!in_array($mdc->id,$mdcids))
                                    {
                                        $mdcids[] = json_decode($mdc->id);
                                    }
                                }
                            }

                        }
                    }
		}else{
                    $modulesData = DB::table('modules')->whereNotNull('mdClasses')->get();
                    foreach($modulesData as $mdc)
                        {
                        if(in_array($selectedClass,json_decode($mdc['mdClasses'])))
                        {
                            if(!in_array($mdc['id'],$mdcids))
                            {
                                $mdcids[] = json_decode($mdc['id']);
                            }
                        }
                    }
		}
            }
            if(count($mdcids)> 0)
            {
                    if($subject == "" && $levelsetfor == "" && $selectedClass !="")
                    {

                        $toReturn["modules"] =  modules::whereIn('id',$mdcids)->get()->toArray();
                        $toReturn["mids"] = $mdcids;
                    }
                    else
                    {
                        if($selectedClass !="" && $subject != "")
                        {
                           
                                 $toReturn["modules"] = modules::whereIn('id',$mdcids)->where("subject",$subject)->where("yearlevel",$yearlevel)->where("gradelevel",$gradelevel)->get()->toArray();
                                 $toReturn["cond"] = "sc=1,sb=1,lv=1";
                        }
                        
                        else if($selectedClass !="" && $subject == "")
                        {
                            $toReturn["modules"] = modules::whereIn('id',$mdcids)->where("yearlevel",$yearlevel)->where("gradelevel",$gradelevel)->get()->toArray();
                        }
                        else if($selectedClass =="" && $subject != "")
                        {
                           $toReturn["modules"] = modules::where("subject",$subject)->where("yearlevel",$yearlevel)->where("gradelevel",$gradelevel)->get()->toArray();
                        }
                        else if($selectedClass == "" && $subject != "")
                        {
                           $toReturn["modules"] = modules::where("subject",$subject)->get()->toArray();
                        }

                    }
            }
                return $toReturn["modules"];
        }
       
        public function viewGradeFeedback($id)
	{
                $assignmentsAnswers = DB::table('assignmentsAnswers')
                ->leftJoin('assignments', 'assignments.id', '=', 'assignmentsAnswers.assignmentId')
                ->select('assignmentsAnswers.id as id',
                         'assignmentsAnswers.assignmentId as assignmentId',        
                         'assignmentsAnswers.gradepoint as gradepoint',
                         'assignmentsAnswers.feedback_received as feedback_received',
                         'assignments.AssignTitle as AssignTitle')
                ->where('assignmentId',$id)
                ->where('userId',$this->data['users']->id)
                ->first();
                $toReturn['assignments'] = $assignmentsAnswers;
                return $toReturn;
        }
}
