<?php
/**
 * 在线课程逻辑层
 * buzhidao
 */
namespace Weixin\Controller;

class CourseController extends CommonController
{
    public function __construct()
    {
        parent::__construct();

        $this->_course_class = C('USER.course_class');
        $this->assign('courseclass', $this->_course_class);

        $this->_user_course_status = C('USER.user_course_status');
        $this->assign('usercoursestatus', $this->_user_course_status);
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

    public function index()
    {
        $userid = $this->userinfo['userid'];

        $classid = $this->_getClassid();
        $this->assign('classid', $classid);

        list($start, $length) = $this->_mkPage();
        $data = D('Course')->getCourse(null, $classid, $userid, $start, $length);
        $total = $data['total'];
        $courselist = $data['data'];

        $this->assign('courselist', $courselist);

        $param = array(
            'classid' => $classid,
        );
        $this->assign('param', $param);
        //解析分页数据
        $this->_mkPagination($total, $param);

        //获取党员已学习的课程 最大的课程id
        $learnedcoursemax = D('Course')->getLearnedCourseidMax($userid);
        //获取应该学习的下一课程id
        $ccourseid = !empty($learnedcoursemax) ? $learnedcoursemax['courseid'] : 0;
        $courseprevnextinfo = D('Course')->getPrevNextCourse($ccourseid);
        $this->assign('clearncourseid', !empty($courseprevnextinfo['next']) ? $courseprevnextinfo['next']['courseid'] : 0);

        $this->display();
    }

    /**
     * 课程详细页 - 播放视频
     */
    public function profile()
    {
        $courseid = $this->_getCourseid();

        $courseinfo = D('Course')->getCourseByID($courseid);
        $this->assign('courseinfo', $courseinfo);

        if (!is_array($courseinfo) || empty($courseinfo)) $this->_gotoIndex();

        //获取上一课程、下一课程
        $courseprevnextinfo = D('Course')->getPrevNextCourse($courseid);
        $this->assign('courseprevnextinfo', $courseprevnextinfo);

        //记录开始学习时间
        if (!$courseinfo['begintime']) {
            M('user_course')->add(array(
                'userid'       => $this->userinfo['userid'],
                'courseid'     => $courseinfo['courseid'],
                'status'       => 0,
                'begintime'    => TIMESTAMP,
                'completetime' => 0,
            ));
        }

        //浏览次数加一
        M('course')->where(array('courseid'=>$courseinfo['courseid']))->save(array('viewnum'=>$courseinfo['viewnum']+1));

        //判断上一课是否已学习 如果未学习 显示去学习的信息
        if (is_array($courseprevnextinfo['prev'])&&!empty($courseprevnextinfo['prev'])&&!$courseprevnextinfo['prev']['status']) {
            $this->assign('errormsg', '请先学习上一课程！');
        }

        //生成coursesign
        $coursesign = md5($courseinfo['courseid'].TIMESTAMP);
        session('coursesign_'.$courseinfo['courseid'], $coursesign);
        $this->assign('coursesign', $coursesign);

        $this->display();
    }

    //课程已学习完 AJAX接口
    public function scomplete()
    {
        $courseid = $this->_getCourseid();

        //如果校验码不对 返回false
        $coursesign = mRequest('coursesign');
        $mycoursesign = session('coursesign_'.$courseid);
        if ($coursesign != $mycoursesign) {
            return false;
        }

        $where = array(
            'userid' => $this->userinfo['userid'],
            'courseid' => $courseid,
        );
        //获取党员对该课程学习信息
        $usercourseinfo = M('user_course')->where($where)->find();

        if (!is_array($usercourseinfo)||empty($usercourseinfo)) return false;

        //标识 党员已学习完课程
        if (!$usercourseinfo['status']) {
            M('user_course')->where($where)->save(array(
                'status' => 1,
            ));

            //课程已学习党员数+1
            M('course')->where(array('courseid'=>$courseid))->setInc('learnnum');
        }

        echo true;
    }
}