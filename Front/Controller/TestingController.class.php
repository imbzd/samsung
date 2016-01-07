<?php
/**
 * 随堂测试模型逻辑控制
 * buzhidao
 * 2015-12-08
 */
namespace Front\Controller;

use Any\Controller;

class TestingController extends CommonController
{
    //导航栏目navflag标识
    public $navflag = 'Testing';

    public function __construct()
    {
        parent::__construct();

        $this->_course_class = C('USER.course_class');
        $this->assign('courseclass', $this->_course_class);
    }

    //获取课程分类Id
    private function _getClassid()
    {
        $classid = mRequest('classid');

        return $classid;
    }

    //获取课程id
    private function _getCourseid()
    {
        $courseid = mRequest('courseid');

        return $courseid;
    }

    //获取试卷id
    private function _getTestingid()
    {
        $testingid = mRequest('testingid');

        return $testingid;
    }

    //随堂测评首页
    public function index()
    {
        $classid = $this->_getClassid();
        $this->assign('classid', $classid);

        list($start, $length) = $this->_mkPage();
        $data = D('Testing')->getTesting(null, null, $classid, $this->userinfo['userid'], $start, $length);
        $total = $data['total'];
        $testinglist = $data['data'];

        $this->assign('testinglist', $testinglist);

        $param = array(
            'classid' => $classid,
        );
        $this->assign('param', $param);
        //解析分页数据
        $this->_mkPagination($total, $param);

        $this->display();
    }

    //随堂测评 试卷页
    public function exam()
    {
        $courseid = $this->_getClassid();
        $testingid = $this->_getTestingid();

        $testinginfo = D('Testing')->getTestingByID($courseid, $testingid);
        $this->assign('testinginfo', $testinginfo);

        if (!is_array($testinginfo) || empty($testinginfo)) {
            $this->assign('errormsg', '试卷信息错误！');
        } else if (!$testinginfo['ucstatus']) {
            $this->assign('errormsg', '请先学习该课程！');
        } else if ($testinginfo['utstatus']) {
            header('location:'.__APP__.'?s=Testing/profile&testingid='.$testingid);
            exit;
        }

        $testingprevnextinfo = D('Testing')->getPrevNextTesting($testingid);
        $this->assign('testingprevnextinfo', $testingprevnextinfo);
        
        session('testingid_'.$testinginfo['testingid'], $testinginfo['testingid']);
        $this->display();
    }

    //批阅试卷
    public function check()
    {
        $userid = $this->userinfo['userid'];

        $testingid = $this->_getTestingid();
        $testingid = session('testingid_'.$testingid);
        $testinginfo = D('Testing')->getTestingByID(null, $testingid);

        if (!is_array($testinginfo) || empty($testinginfo) || $testinginfo['utstatus']) $this->_gotoIndex();

        //获取用户答案
        $exams = mRequest('exams', false);

        //比较答案
        $usertesting = array();
        $usertestingresult = array();
        $rightexam = 0;
        $wrongexam = 0;
        $gotscore = 0;
        foreach ($testinginfo['exams'] as $k=>$exam) {
            if (!isset($exams[$exam['examid']])) {
                header('location:'.__APP__.'?s=Testing/exam&testingid='.$testingid);
                exit;
            }

            $useranswer = is_array($exams[$exam['examid']]) ? implode('', $exams[$exam['examid']]) : $exams[$exam['examid']];
            $usertestingresult[] = array(
                'userid' => $userid,
                'testingid' => $testingid,
                'examid' => $exam['examid'],
                'useranswer' => $useranswer,
                'officeanswer' => $exam['answer'],
                'result' => $useranswer==$exam['answer']?1:0
            );
            if ($useranswer == $exam['answer']) {
                $rightexam++;
                $gotscore+=$exam['score'];
            } else {
                $wrongexam++;
            }

            $testinginfo['exams'][$k]['useranswer'] = $useranswer;
            $testinginfo['exams'][$k]['result'] = $useranswer==$exam['answer']?1:0;
        }

        $usertesting = array(
            'userid' => $userid,
            'testingid' => $testingid,
            'status' => 1,
            'rightexam' => $rightexam,
            'wrongexam' => $wrongexam,
            'gotscore' => $gotscore,
            'completetime' => TIMESTAMP,
        );
        $usertestingresult = $usertestingresult;

        if ((int)$gotscore >= (int)$testinginfo['passscore']) {
            //及格
            
            M('testing')->startTrans();

            $result1 = M('user_testing')->add($usertesting);
            $result2 = M('user_testing_result')->addAll($usertestingresult);
            $result3 = M('user_course')->where(array('userid'=>$userid,'courseid'=>$testinginfo['courseid']))->save(array('status'=>2));
            if ($result1 && $result2 && $result3) {
                M('testing')->commit();
            } else {
                M('testing')->rollback();
            }

            header('location:'.__APP__.'?s=Testing/profile&testingid='.$testingid);
            exit;
        } else {
            //不及格
            $testinginfo['utstatus'] = 0;
            $testinginfo['rightexam'] = $rightexam;
            $testinginfo['wrongexam'] = $wrongexam;
            $testinginfo['gotscore'] = $gotscore;
            $testinginfo['completetime'] = 0;

            $this->assign('testinginfo', $testinginfo);
            $this->display('Testing/profile');
        }
    }

    //随堂测评结果页
    public function profile()
    {
        $userid = $this->userinfo['userid'];

        $testingid = $this->_getTestingid();
        $testinginfo = D('Testing')->getTestingByID(null, $testingid);

        if (!is_array($testinginfo) || empty($testinginfo) || !$testinginfo['utstatus']) $this->_gotoIndex();

        //获取用户做的试卷答案
        $usertestingresult = M('user_testing_result')->where(array('userid'=>$userid,'testingid'=>$testingid))->select();
        foreach ($testinginfo['exams'] as $k=>$exam) {
            foreach ($usertestingresult as $exami) {
                if ($exam['examid'] == $exami['examid']) {
                    $testinginfo['exams'][$k]['useranswer'] = $exami['useranswer'];
                    $testinginfo['exams'][$k]['result'] = $exami['result'];
                }
            }
        }
        $this->assign('testinginfo', $testinginfo);

        $testingprevnextinfo = D('Testing')->getPrevNextTesting($testingid);
        $this->assign('testingprevnextinfo', $testingprevnextinfo);
        
        $this->display();
    }
}