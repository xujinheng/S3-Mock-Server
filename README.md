# S3-Mock-Server

[![Github All Releases](https://img.shields.io/github/downloads/xujinheng/S3-Mock-Server/total.svg)](https://github.com/xujinheng/S3-Mock-Server/releases/)
[![GitHub License](https://img.shields.io/github/license/xujinheng/S3-Mock-Server.svg?style=flat-square)](https://github.com/xujinheng/S3-Mock-Server/blob/master/LICENSE)

### Introduction
`S3-Mock-Server` is a PHP based server that implements Amazon S3 API. 

### Usage
Download the file in php server:
```bash
curl -L https://github.com/xujinheng/S3-Mock-Server/releases/download/0.0.1/server-single-file.php -o server.php
```

### [Demo.ipynb](./demo.ipynb) 
Supported methods being tested by [boto3](https://github.com/boto/boto3):
- Buckets
  - Create
  - List
  - Delete
- Objects
  - Create
  - List (prefix)
  - Download
  - Delete

