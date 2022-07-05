# S3-Mock-Server

[![Github All Releases](https://img.shields.io/github/downloads/xujinheng/S3-Mock-Server/total.svg)](https://github.com/xujinheng/S3-Mock-Server/releases/)
[![GitHub License](https://img.shields.io/github/license/xujinheng/S3-Mock-Server.svg?style=flat-square)](https://github.com/xujinheng/S3-Mock-Server/blob/master/LICENSE)

### Introduction
`S3-Mock-Server` is a PHP based server that implements Amazon S3 API. 

### Deployment
Download the file in php server:
```bash
curl -L https://github.com/xujinheng/S3-Mock-Server/releases/download/0.0.1/server-single-file.php -o server.php
```

### Usage
```python
import boto3
s3 = boto3.resource("s3", endpoint_url=<the place you put server.php>, 
                    aws_access_key_id=<any string>, aws_secret_access_key=<any string>)
s3.Bucket(<bucket_name>).upload_file(<local_file>, <object_key>)
s3.Bucket(<bucket_name>).download_file(<object_key>, <local_path>)
```
Check [demo.ipynb](./demo.ipynb) for details.

Supported client: 
- [boto3](https://github.com/boto/boto3)

Supported methods:
- Buckets
  - Create
  - List
  - Delete
- Objects
  - Create
  - List (prefix)
  - Download
  - Delete

