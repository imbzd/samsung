<?php
/**
 * 会员数据模型
 * 2015-12-22
 * buzhidao
 */
namespace Front\Model;

class CourseModel extends CommonModel
{
    public function __construct()
    {
        parent::__construct();
    }

    //获取课程信息
    public function getCourse($courseid=null, $classid=null, $start=0, $length=9999)
    {
        $where = array();
        if ($courseid) $where['courseid'] = $courseid;
        if ($classid) $where['classid'] = $classid;

        $count = M('course')->where($where)->count();
        $result = M('course')->where($where)->order('createtime desc')->limit($start, $length)->select();

        return array('total'=>$count, 'data'=>is_array($result)?$result:array());
    }

    //获取课程详情
    public function getCourseByID($courseid=null)
    {
        if (!$courseid) return false;

        $datainfo = $this->getCourse($courseid);
        $courseinfo = $datainfo['total'] ? $datainfo['data'][0] : array();

        //获取复习资料
        if (!empty($courseinfo)) {
            $reviewinfo = M('course_review')->where(array('courseid'=>$courseinfo['courseid']))->find();
            $courseinfo['reviewinfo'] = is_array($reviewinfo)&&!empty($reviewinfo) ? $reviewinfo : array();
        }

        return $courseinfo;
    }

    //获取课程总数
    public function getCoursenum()
    {
        $where = array(
            'isshow' => 1,
        );
        $result = M('course')->where($where)->count();

        return $result>0 ? $result : 0;
    }
}