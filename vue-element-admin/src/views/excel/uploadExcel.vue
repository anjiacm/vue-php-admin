<template>
  <div class="app-container">
    <upload-excel-component :on-success="handleSuccess" :before-upload="beforeUpload" />
    <el-table :data="tableData" border highlight-current-row style="width: 100%;margin-top:20px;">
      <el-table-column v-for="item of tableHeader" :prop="item" :label="item" :key="item" />
    </el-table>
    <h3>后端处理样例</h3>
    <li>el-upload 上传至服务器</li>
    <li>服务器 php codeguy/upload 处理</li>
    <li>服务器 PhpOffice/PhpSpreadsheet 解析excel 插入mysql</li>
    <el-upload
      :on-preview="handlePreview"
      :on-remove="handleRemove"
      :before-remove="beforeRemove"
      :file-list="fileList"
      :limit="3"
      :on-exceed="handleExceed"
      :on-success="onSuccess"
      :action="action_url"
      class="upload-demo"
      multiple
    >
      <el-button size="small" type="primary">点击上传</el-button>
      <div slot="tip" class="el-upload__tip">只能上传jpg/png文件，且不超过500kb与后端判断一致</div>
    </el-upload>
    <h4>el-upload file-list 文件点击下载 on-preview 勾子函数里 axios 下载对象并重命名保存文件</h4>
  </div>
</template>

<script>
import UploadExcelComponent from '@/components/UploadExcel/index.vue'
import axios from 'axios'

export default {
  name: 'UploadExcel',
  components: { UploadExcelComponent },
  data() {
    return {
      action_url: process.env.BASE_API + 'article/upload', // => http://www.cirest.com:8890/api/v2/article/upload
      tableData: [],
      tableHeader: [],
      fileList: []
    }
  },
  methods: {
    // php server_side
    handleRemove(file, fileList) {
      console.log(file, fileList)
    },
    handlePreview(file) {
      console.log('handlePreview', file)
      const url = file.response.url
      // 下载文件 el-upload on-preview 勾子里 开启下载 blob
      import('axios').then(() => {
        axios({
          method: 'get',
          url: url, // url地址
          responseType: 'blob' // 指定响应类型
        }).then(res => {
          console.log('res.........', res)
          if (!res.data) {
            this.$message.warning('文件下载失败')
            return
          }
          if (typeof window.navigator.msSaveBlob !== 'undefined') {
            // 浏览器兼容性检测
            window.navigator.msSaveBlob(new Blob([res.data]), file.name)
          } else {
            const url = window.URL.createObjectURL(res.data)
            const link = document.createElement('a')
            link.style.display = 'none'
            link.href = url
            link.setAttribute('download', file.name) // 重命名文件
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link) // 下载完成移除元素
            window.URL.revokeObjectURL(url) // 释放掉blob对象
          }
        })
      })
    },
    handleExceed(files, fileList) {
      this.$message.warning(
        `当前限制选择 3 个文件，本次选择了 ${
          files.length
        } 个文件，共选择了 ${files.length + fileList.length} 个文件`
      )
    },
    beforeRemove(file, fileList) {
      return this.$confirm(`确定移除 ${file.name}？`)
    },
    onSuccess(response, file, fileList) {
      console.log('onSuccess response', response, file, fileList)
      this.$message(response.message)
    },
    // php codeguy/upload end

    beforeUpload(file) {
      const isLt1M = file.size / 1024 / 1024 < 1

      if (isLt1M) {
        return true
      }

      this.$message({
        message: 'Please do not upload files larger than 1m in size.',
        type: 'warning'
      })
      return false
    },
    handleSuccess({ results, header }) {
      this.tableData = results
      this.tableHeader = header
    }
  }
}
</script>
